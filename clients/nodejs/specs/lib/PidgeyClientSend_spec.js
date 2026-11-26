import { PidgeyClient } from '../../lib/PidgeyClient.js';
import { PidgeyError } from '../../lib/PidgeyError.js';
import { SUCCESS_BODY, errorReply } from '../support/Fixtures.js';
import { TestServer } from '../support/TestServer.js';

const TOKEN = 'pdg_secret_token';
const MESSAGE = { to: 'user@example.com', subject: 'Hello', text: 'Hi!' };

describe('PidgeyClient#send', () => {
  let server;

  const clientFor = (extra = {}) => new PidgeyClient({ token: TOKEN, baseUrl: server.baseUrl, ...extra });
  const start = async (script) => {
    server = await TestServer.start(script);
  };
  const failureOf = (promise) => promise.then(
    () => { throw new Error('expected a rejection'); },
    (error) => error,
  );

  afterEach(() => server.stop());

  describe('success', () => {
    it('resolves with the body as returned, plus replayed: false', async () => {
      await start({ json: SUCCESS_BODY });

      expect(await clientFor().send(MESSAGE)).toEqual({ ...SUCCESS_BODY, replayed: false });
    });

    it('passes an off-contract field added by the API through', async () => {
      await start({ json: { ...SUCCESS_BODY, queueId: 'abc' } });

      expect((await clientFor().send(MESSAGE)).queueId).toBe('abc');
    });

    it('resolves an off-contract 200 without smtp unchanged', async () => {
      await start({ json: { status: 'sent' } });

      expect(await clientFor().send(MESSAGE)).toEqual({ status: 'sent', replayed: false });
    });

    it('sets replayed: true from the Idempotent-Replayed header', async () => {
      await start({ json: SUCCESS_BODY, headers: { 'Idempotent-Replayed': 'true' } });

      expect((await clientFor().send(MESSAGE)).replayed).toBe(true);
    });

    it('sets replayed: false for another header value', async () => {
      await start({ json: SUCCESS_BODY, headers: { 'Idempotent-Replayed': 'false' } });

      expect((await clientFor().send(MESSAGE)).replayed).toBe(false);
    });

    it('does not let the body override replayed', async () => {
      await start({ json: { ...SUCCESS_BODY, replayed: true } });

      expect((await clientFor().send(MESSAGE)).replayed).toBe(false);
    });

    it('reads a gzip body', async () => {
      await start({ json: SUCCESS_BODY, gzip: true });

      expect(await clientFor().send(MESSAGE)).toEqual({ ...SUCCESS_BODY, replayed: false });
    });

    it('works with an options argument of null', async () => {
      await start({ json: SUCCESS_BODY });

      expect((await clientFor().send(MESSAGE, null)).status).toBe('sent');
    });
  });

  describe('arguments', () => {
    beforeEach(() => start());

    for (const message of [undefined, null, [], 'text', 42]) {
      it(`rejects ${JSON.stringify(message)} as the message without a request`, async () => {
        const error = await failureOf(clientFor().send(message));

        expect(error).toBeInstanceOf(PidgeyError);
        expect(error.code).toBe('CLIENT_CONFIGURATION');
        expect(error.possiblySent).toBe(false);
        expect(server.requests.length).toBe(0);
      });
    }

    for (const key of ['', 'x'.repeat(256), 'has space', 'café', 42, null]) {
      it(`rejects the idempotency key ${JSON.stringify(key)} without a request, never echoing it`, async () => {
        const error = await failureOf(clientFor().send(MESSAGE, { idempotencyKey: key }));

        expect(error.code).toBe('CLIENT_CONFIGURATION');
        expect(error.possiblySent).toBe(false);
        expect(error.message).not.toContain('has space');
        expect(server.requests.length).toBe(0);
      });
    }

    it('rejects a signal that is not an AbortSignal', async () => {
      const error = await failureOf(clientFor().send(MESSAGE, { signal: {} }));

      expect(error.code).toBe('CLIENT_CONFIGURATION');
      expect(server.requests.length).toBe(0);
    });

    it('rejects, never throws synchronously', () => {
      let promise;

      expect(() => { promise = clientFor().send(); }).not.toThrow();
      return expectAsync(promise).toBeRejected();
    });
  });

  describe('API failures', () => {
    it('maps the envelope', async () => {
      await start(errorReply('NO_SMTP_CONNECTION', 409, { message: 'No SMTP connection configured' }));
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error).toBeInstanceOf(PidgeyError);
      expect(error.code).toBe('NO_SMTP_CONNECTION');
      expect(error.message).toBe('No SMTP connection configured');
      expect(error.statusCode).toBe(409);
      expect(error.possiblySent).toBe(false);
      expect(error.body).toContain('NO_SMTP_CONNECTION');
    });

    it('maps validation details', async () => {
      await start(errorReply('VALIDATION_FAILED', 400, { message: 'to must be an email', details: ['to must be an email'] }));
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error.details).toEqual(['to must be an email']);
    });

    it('maps the smtp reply and possiblySent of an SMTP timeout', async () => {
      await start(errorReply('SMTP_TIMEOUT', 504, { message: 'SMTP timeout', smtp: { code: 421, message: 'slow' } }));
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error.smtp).toEqual({ code: 421, message: 'slow' });
      expect(error.possiblySent).toBe(true);
    });

    it('redacts a token reflected by the server', async () => {
      await start(errorReply('SMTP_REJECTED', 502, { message: `echo ${TOKEN}` }));
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error.message).toBe('echo [redacted]');
      expect(error.body).not.toContain(TOKEN);
    });

    it('maps a non-JSON error body to INVALID_RESPONSE keeping the status', async () => {
      await start({ status: 502, body: '<html>Bad gateway</html>' });
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error.code).toBe('INVALID_RESPONSE');
      expect(error.statusCode).toBe(502);
      expect(error.possiblySent).toBe(false);
    });

    it('maps a non-JSON 200 to a possibly sent INVALID_RESPONSE', async () => {
      await start({ status: 200, body: 'OK' });
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error.code).toBe('INVALID_RESPONSE');
      expect(error.statusCode).toBe(200);
      expect(error.possiblySent).toBe(true);
    });

    it('maps a 200 body over 64 KiB to a possibly sent INVALID_RESPONSE', async () => {
      await start({ status: 200, body: 'x'.repeat(70 * 1024) });
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error.code).toBe('INVALID_RESPONSE');
      expect(error.possiblySent).toBe(true);
      expect(Object.hasOwn(error, 'body')).toBe(false);
    });

    it('maps an error body over 64 KiB to an INVALID_RESPONSE that was not sent', async () => {
      await start({ status: 502, body: 'x'.repeat(70 * 1024) });
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error.code).toBe('INVALID_RESPONSE');
      expect(error.statusCode).toBe(502);
      expect(error.possiblySent).toBe(false);
    });

    it('does not follow a redirect and keeps its status', async () => {
      await start((request) => (request.url === '/v1/messages'
        ? { status: 302, headers: { Location: '/elsewhere' } }
        : { json: SUCCESS_BODY }));
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error.code).toBe('INVALID_RESPONSE');
      expect(error.statusCode).toBe(302);
      expect(error.possiblySent).toBe(false);
      expect(server.requests.length).toBe(1);
    });
  });

  describe('network failures', () => {
    it('reports a refused connection as an unambiguous NETWORK_ERROR', async () => {
      await start();
      const client = clientFor({ maxRetries: 0 });
      await server.stop();
      server = await TestServer.start();
      const error = await failureOf(client.send(MESSAGE));

      expect(error).toBeInstanceOf(PidgeyError);
      expect(error.code).toBe('NETWORK_ERROR');
      expect(error.possiblySent).toBe(false);
      expect(error.statusCode).toBeUndefined();
      expect(Object.hasOwn(error, 'body')).toBe(false);
    });

    it('reports a connection destroyed after the request as an ambiguous NETWORK_ERROR', async () => {
      await start({ destroy: true });
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(server.requests.length).toBe(1);
      expect(error.code).toBe('NETWORK_ERROR');
      expect(error.possiblySent).toBe(true);
    });

    it('reports a timeout as an ambiguous NETWORK_ERROR', async () => {
      await start({ json: SUCCESS_BODY, delay: 500 });
      const error = await failureOf(clientFor({ timeoutMs: 50 }).send(MESSAGE));

      expect(error.code).toBe('NETWORK_ERROR');
      expect(error.possiblySent).toBe(true);
      expect(error.message).toMatch(/timed out/);
    });

    it('reports a timeout while reading the body as an ambiguous NETWORK_ERROR', async () => {
      await start({ json: SUCCESS_BODY, stall: true });
      const error = await failureOf(clientFor({ timeoutMs: 50 }).send(MESSAGE));

      expect(error.code).toBe('NETWORK_ERROR');
      expect(error.possiblySent).toBe(true);
    });

    it('never puts the token or the URL in the message', async () => {
      await start({ destroy: true });
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error.message).not.toContain(TOKEN);
      expect(error.message).not.toContain(server.baseUrl);
    });
  });
});
