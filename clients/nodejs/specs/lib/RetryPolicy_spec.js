import { PidgeyClient } from '../../lib/PidgeyClient.js';
import { TestServer } from '../support/TestServer.js';

const TOKEN = 'pdg_secret_token';
const MESSAGE = { to: 'user@example.com', subject: 'Hello', text: 'Hi!' };
const OK = { status: 'sent', smtp: { code: 250, message: 'OK: queued as 1a2b3c' } };

const envelope = (code, statusCode, extra = {}) => ({
  json: { error: { code, message: `${code} happened`, ...extra }, statusCode, timestamp: '2026-10-25T12:00:00.000Z' },
  status: statusCode,
});
const success = { json: OK };

describe('Retry policy', () => {
  let server;

  const clientFor = (extra = {}) => new PidgeyClient({
    token: TOKEN, baseUrl: server.baseUrl, retryDelayMs: 1, ...extra,
  });
  const start = async (script) => {
    server = await TestServer.start(script);
  };
  const failureOf = (promise) => promise.then(
    () => { throw new Error('expected a rejection'); },
    (error) => error,
  );

  afterEach(() => server.stop());

  describe('retried replies', () => {
    [
      ['SMTP_CONNECTION_FAILED', 502],
      ['SMTP_TEMPORARY_FAILURE', 503],
      ['SMTP_NEW_THING', 503],
    ].forEach(([code, status]) => {
      it(`retries ${code} on ${status} without retryAmbiguous`, async () => {
        await start([envelope(code, status), success]);
        const result = await clientFor().send(MESSAGE);

        expect(result.status).toBe('sent');
        expect(server.requests.length).toBe(2);
      });
    });

    [
      ['SMTP_TIMEOUT', 504],
      ['SMTP_NEW_THING', 504],
    ].forEach(([code, status]) => {
      it(`does not retry ${code} on ${status} by default, and reports it possibly sent`, async () => {
        await start([envelope(code, status), success]);
        const error = await failureOf(clientFor().send(MESSAGE));

        expect(error.code).toBe(code);
        expect(error.possiblySent).toBe(true);
        expect(server.requests.length).toBe(1);
      });

      it(`retries ${code} on ${status} with retryAmbiguous`, async () => {
        await start([envelope(code, status), success]);
        const result = await clientFor({ retryAmbiguous: true }).send(MESSAGE);

        expect(result.status).toBe('sent');
        expect(server.requests.length).toBe(2);
      });
    });
  });

  describe('replies that are never retried', () => {
    [
      ['SMTP_REJECTED', 502, false],
      ['SMTP_AUTH_FAILED', 502, false],
      ['SMTP_TLS_FAILED', 502, false],
      ['SMTP_PROTOCOL_ERROR', 502, true],
      ['SMTP_NEW_THING', 502, false],
      ['VALIDATION_FAILED', 400, false],
      ['BAD_REQUEST', 400, false],
      ['UNAUTHORIZED', 401, false],
      ['FORBIDDEN', 403, false],
      ['NO_SMTP_CONNECTION', 409, false],
      ['IDEMPOTENCY_KEY_IN_USE', 409, true],
      ['IDEMPOTENCY_KEY_REUSED', 422, false],
      ['UNPROCESSABLE', 422, false],
      ['TOO_MANY_REQUESTS', 429, false],
      ['INTERNAL_SERVER_ERROR', 500, false],
    ].forEach(([code, status, possiblySent]) => {
      [false, true].forEach((retryAmbiguous) => {
        it(`rejects ${code} on ${status} after one request, with retryAmbiguous ${retryAmbiguous}`, async () => {
          await start([envelope(code, status), success]);
          const error = await failureOf(clientFor({ retryAmbiguous }).send(MESSAGE));

          expect(error.code).toBe(code);
          expect(error.statusCode).toBe(status);
          expect(error.possiblySent).toBe(possiblySent);
          expect(server.requests.length).toBe(1);
        });
      });
    });

    it('does not retry a 3xx', async () => {
      await start([{ status: 302, headers: { Location: '/elsewhere' } }, success]);
      const error = await failureOf(clientFor({ retryAmbiguous: true }).send(MESSAGE));

      expect(error.code).toBe('INVALID_RESPONSE');
      expect(server.requests.length).toBe(1);
    });

    it('does not retry a non-JSON 200, even with retryAmbiguous', async () => {
      await start([{ status: 200, body: 'OK' }, success]);
      const error = await failureOf(clientFor({ retryAmbiguous: true }).send(MESSAGE));

      expect(error.code).toBe('INVALID_RESPONSE');
      expect(error.possiblySent).toBe(true);
      expect(server.requests.length).toBe(1);
    });
  });

  describe('network errors', () => {
    it('retries a refused connection, waiting between the attempts', async () => {
      await start();
      const client = clientFor({ retryDelayMs: 40, maxRetries: 2 });
      await server.stop();
      server = await TestServer.start();
      const started = Date.now();
      const error = await failureOf(client.send(MESSAGE));

      expect(error.code).toBe('NETWORK_ERROR');
      expect(error.possiblySent).toBe(false);
      expect(Date.now() - started).toBeGreaterThanOrEqual(110);
    });

    it('does not retry a destroyed socket by default', async () => {
      await start({ destroy: true });
      const error = await failureOf(clientFor().send(MESSAGE));

      expect(error.code).toBe('NETWORK_ERROR');
      expect(error.possiblySent).toBe(true);
      expect(server.requests.length).toBe(1);
    });

    it('retries a destroyed socket with retryAmbiguous', async () => {
      await start({ destroy: true });
      const error = await failureOf(clientFor({ retryAmbiguous: true }).send(MESSAGE));

      expect(error.code).toBe('NETWORK_ERROR');
      expect(server.requests.length).toBe(3);
    });

    it('does not retry a timeout by default', async () => {
      await start({ json: OK, delay: 300 });
      const error = await failureOf(clientFor({ timeoutMs: 30 }).send(MESSAGE));

      expect(error.code).toBe('NETWORK_ERROR');
      expect(error.possiblySent).toBe(true);
      expect(server.requests.length).toBe(1);
    });

    it('retries a timeout with retryAmbiguous, and resolves when a later attempt answers', async () => {
      await start([{ json: OK, delay: 300 }, success]);
      const result = await clientFor({ timeoutMs: 100, retryAmbiguous: true }).send(MESSAGE);

      expect(result.status).toBe('sent');
      expect(server.requests.length).toBe(2);
    });
  });

  describe('attempts', () => {
    [0, 1, 2].forEach((maxRetries) => {
      it(`makes ${maxRetries + 1} attempt(s) with maxRetries ${maxRetries}`, async () => {
        await start(envelope('SMTP_CONNECTION_FAILED', 502));
        const error = await failureOf(clientFor({ maxRetries }).send(MESSAGE));

        expect(error.code).toBe('SMTP_CONNECTION_FAILED');
        expect(server.requests.length).toBe(maxRetries + 1);
      });
    });

    it('makes three attempts by default', async () => {
      await start(envelope('SMTP_TEMPORARY_FAILURE', 503));
      await failureOf(clientFor().send(MESSAGE));

      expect(server.requests.length).toBe(3);
    });

    const changing = [
      envelope('SMTP_CONNECTION_FAILED', 502),
      envelope('SMTP_TEMPORARY_FAILURE', 503),
      envelope('SMTP_NEW_THING', 503),
      success,
    ];

    it('rejects the last error when the retries run out', async () => {
      await start(changing);
      const error = await failureOf(clientFor({ maxRetries: 2 }).send(MESSAGE));

      expect(error.code).toBe('SMTP_NEW_THING');
      expect(server.requests.length).toBe(3);
    });

    it('rejects the second error with maxRetries 1', async () => {
      await start(changing);
      const error = await failureOf(clientFor({ maxRetries: 1 }).send(MESSAGE));

      expect(error.code).toBe('SMTP_TEMPORARY_FAILURE');
    });

    it('resolves when a retry within the budget succeeds', async () => {
      await start(changing);
      const result = await clientFor({ maxRetries: 3 }).send(MESSAGE);

      expect(result.status).toBe('sent');
      expect(server.requests.length).toBe(4);
    });
  });

  describe('backoff', () => {
    it('waits longer before each retry', async () => {
      const times = [];
      await start(() => {
        times.push(Date.now());
        return envelope('SMTP_TEMPORARY_FAILURE', 503);
      });
      await failureOf(clientFor({ retryDelayMs: 20, maxRetries: 2 }).send(MESSAGE));
      const [first, second] = [times[1] - times[0], times[2] - times[1]];

      expect(first).toBeGreaterThanOrEqual(15);
      expect(second).toBeGreaterThanOrEqual(35);
      expect(second).toBeGreaterThan(first);
    });
  });

  describe('possiblySent across attempts', () => {
    it('is true when an earlier attempt was ambiguous, even if the last one was not', async () => {
      await start([envelope('SMTP_TIMEOUT', 504), envelope('SMTP_CONNECTION_FAILED', 502)]);
      const error = await failureOf(clientFor({ retryAmbiguous: true, maxRetries: 2 }).send(MESSAGE));

      expect(error.code).toBe('SMTP_CONNECTION_FAILED');
      expect(error.possiblySent).toBe(true);
      expect(server.requests.length).toBe(3);
    });

    it('is false when no attempt was ambiguous', async () => {
      await start(envelope('SMTP_CONNECTION_FAILED', 502));
      const error = await failureOf(clientFor({ retryAmbiguous: true }).send(MESSAGE));

      expect(error.possiblySent).toBe(false);
    });
  });

  describe('Idempotency-Key', () => {
    const keysOf = () => server.requests.map((request) => request.headers['idempotency-key']);

    it('sends the caller key on every attempt', async () => {
      await start(envelope('SMTP_CONNECTION_FAILED', 502));
      await failureOf(clientFor().send(MESSAGE, { idempotencyKey: 'my-key-1' }));

      expect(keysOf()).toEqual(['my-key-1', 'my-key-1', 'my-key-1']);
    });

    it('generates one key per call and a new one for the next call', async () => {
      await start(envelope('SMTP_CONNECTION_FAILED', 502));
      const client = clientFor();
      await failureOf(client.send(MESSAGE));
      await failureOf(client.send(MESSAGE));
      const keys = keysOf();

      expect(new Set(keys.slice(0, 3)).size).toBe(1);
      expect(new Set(keys.slice(3)).size).toBe(1);
      expect(keys[0]).not.toBe(keys[3]);
    });

    it('sends the same body on every attempt', async () => {
      await start(envelope('SMTP_CONNECTION_FAILED', 502));
      await failureOf(clientFor().send(MESSAGE));

      expect(new Set(server.requests.map((request) => request.body)).size).toBe(1);
    });
  });
});
