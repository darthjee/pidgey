import { PidgeyClient } from '../../lib/PidgeyClient.js';
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

  describe('abort', () => {
    beforeEach(() => start());

    it('rejects ABORTED before any request when the signal is already aborted', async () => {
      const error = await failureOf(clientFor().send(MESSAGE, { signal: AbortSignal.abort() }));

      expect(error.code).toBe('ABORTED');
      expect(error.possiblySent).toBe(false);
      expect(server.requests.length).toBe(0);
    });

    it('rejects ABORTED, possibly sent, when aborted in flight', async () => {
      await server.stop();
      server = await TestServer.start({ json: SUCCESS_BODY, delay: 1000 });
      const controller = new AbortController();
      const promise = failureOf(clientFor().send(MESSAGE, { signal: controller.signal }));
      setTimeout(() => controller.abort(), 50);
      const error = await promise;

      expect(error.code).toBe('ABORTED');
      expect(error.possiblySent).toBe(true);
    });

    it('rejects ABORTED, possibly sent, when aborted while reading the body of a 400', async () => {
      await server.stop();
      server = await TestServer.start({ ...errorReply('VALIDATION_FAILED', 400, { message: 'bad' }), stall: true });
      const controller = new AbortController();
      const promise = failureOf(clientFor().send(MESSAGE, { signal: controller.signal }));
      setTimeout(() => controller.abort(), 100);
      const error = await promise;

      expect(error.code).toBe('ABORTED');
      expect(error.possiblySent).toBe(true);
    });

    it('prefers ABORTED over the timeout when the caller aborted', async () => {
      await server.stop();
      server = await TestServer.start({ json: SUCCESS_BODY, delay: 1000 });
      const error = await failureOf(clientFor({ timeoutMs: 50 }).send(MESSAGE, { signal: AbortSignal.timeout(20) }));

      expect(error.code).toBe('ABORTED');
    });

    it('accepts a signal that never fires', async () => {
      const result = await clientFor().send(MESSAGE, { signal: new AbortController().signal });

      expect(result.status).toBe('sent');
    });
  });

  describe('concurrency', () => {
    it('keeps two concurrent sends independent', async () => {
      await start((request) => (JSON.parse(request.body).subject === 'slow'
        ? { json: { status: 'slow' }, delay: 100 }
        : { json: { status: 'fast' } }));
      const client = clientFor();
      const [slow, fast] = await Promise.all([
        client.send({ ...MESSAGE, subject: 'slow' }),
        client.send({ ...MESSAGE, subject: 'fast' }),
      ]);

      expect(slow.status).toBe('slow');
      expect(fast.status).toBe('fast');
      const keys = server.requests.map((request) => request.headers['idempotency-key']);
      expect(new Set(keys).size).toBe(2);
    });

    it('lets one failing send leave another one untouched', async () => {
      await start((request) => (JSON.parse(request.body).subject === 'bad'
        ? errorReply('VALIDATION_FAILED', 400, { message: 'bad' })
        : { json: SUCCESS_BODY }));
      const client = clientFor();
      const [bad, good] = await Promise.allSettled([
        client.send({ ...MESSAGE, subject: 'bad' }),
        client.send({ ...MESSAGE, subject: 'good' }),
      ]);

      expect(bad.status).toBe('rejected');
      expect(good.status).toBe('fulfilled');
    });
  });
});
