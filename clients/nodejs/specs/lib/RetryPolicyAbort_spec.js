import { PidgeyClient } from '../../lib/PidgeyClient.js';
import { SUCCESS_BODY, SUCCESS_REPLY, errorReply } from '../support/Fixtures.js';
import { TestServer } from '../support/TestServer.js';

const TOKEN = 'pdg_secret_token';
const MESSAGE = { to: 'user@example.com', subject: 'Hello', text: 'Hi!' };

const success = SUCCESS_REPLY;

describe('Retry policy abort', () => {
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
  const sleep = (ms) => new Promise((resolve) => { setTimeout(resolve, ms); });

  afterEach(() => server.stop());

  it('rejects ABORTED, not sent, before any request', async () => {
    await start();
    const error = await failureOf(clientFor().send(MESSAGE, { signal: AbortSignal.abort() }));

    expect(error.code).toBe('ABORTED');
    expect(error.possiblySent).toBe(false);
    expect(server.requests.length).toBe(0);
  });

  it('rejects ABORTED, possibly sent, in flight, without retrying', async () => {
    await start({ json: SUCCESS_BODY, delay: 1000 });
    const controller = new AbortController();
    const promise = failureOf(clientFor({ retryAmbiguous: true }).send(MESSAGE, { signal: controller.signal }));
    setTimeout(() => controller.abort(), 50);
    const error = await promise;

    expect(error.code).toBe('ABORTED');
    expect(error.possiblySent).toBe(true);
    expect(server.requests.length).toBe(1);
  });

  it('rejects ABORTED, possibly sent, while reading the body', async () => {
    await start({ ...success, stall: true });
    const controller = new AbortController();
    const promise = failureOf(clientFor({ retryAmbiguous: true }).send(MESSAGE, { signal: controller.signal }));
    setTimeout(() => controller.abort(), 100);
    const error = await promise;

    expect(error.code).toBe('ABORTED');
    expect(error.possiblySent).toBe(true);
  });

  [['VALIDATION_FAILED', 400], ['UNAUTHORIZED', 401], ['FORBIDDEN', 403], ['NO_SMTP_CONNECTION', 409]].forEach(([code, status]) => {
    it(`rejects ABORTED, possibly sent, while reading the body of a ${status}`, async () => {
      await start({ ...errorReply(code, status), stall: true });
      const controller = new AbortController();
      const promise = failureOf(clientFor().send(MESSAGE, { signal: controller.signal }));
      setTimeout(() => controller.abort(), 100);
      const error = await promise;

      expect(error.code).toBe('ABORTED');
      expect(error.possiblySent).toBe(true);
      expect(server.requests.length).toBe(1);
    });
  });

  it('rejects ABORTED, not sent, during the backoff after unambiguous failures', async () => {
    await start(errorReply('SMTP_CONNECTION_FAILED', 502));
    const controller = new AbortController();
    const started = Date.now();
    const promise = failureOf(clientFor({ retryDelayMs: 5000 }).send(MESSAGE, { signal: controller.signal }));
    setTimeout(() => controller.abort(), 100);
    const error = await promise;

    expect(error.code).toBe('ABORTED');
    expect(error.possiblySent).toBe(false);
    expect(Date.now() - started).toBeLessThan(3000);
    await sleep(50);
    expect(server.requests.length).toBe(1);
  });

  it('rejects ABORTED, possibly sent, during the backoff after an ambiguous attempt', async () => {
    await start(errorReply('SMTP_TIMEOUT', 504));
    const controller = new AbortController();
    const promise = failureOf(clientFor({ retryDelayMs: 5000, retryAmbiguous: true })
      .send(MESSAGE, { signal: controller.signal }));
    setTimeout(() => controller.abort(), 100);
    const error = await promise;

    expect(error.code).toBe('ABORTED');
    expect(error.possiblySent).toBe(true);
    expect(server.requests.length).toBe(1);
  });
});
