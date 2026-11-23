import { inspect } from 'node:util';
import { PidgeyClient } from '../../lib/PidgeyClient.js';
import { PidgeyError } from '../../lib/PidgeyError.js';
import { TestServer } from '../support/TestServer.js';

const TOKEN = 'pdg_secret_token';

describe('PidgeyClient', () => {
  describe('constructor', () => {
    it('builds a client from valid options', () => {
      expect(new PidgeyClient({ token: TOKEN, baseUrl: 'https://pidgey.example.com' }))
        .toBeInstanceOf(PidgeyClient);
    });

    it('throws a CLIENT_CONFIGURATION PidgeyError synchronously', () => {
      let caught;
      try {
        new PidgeyClient();
      } catch (error) {
        caught = error;
      }

      expect(caught).toBeInstanceOf(PidgeyError);
      expect(caught.code).toBe('CLIENT_CONFIGURATION');
    });
  });

  describe('inspectable state', () => {
    const client = new PidgeyClient({ token: TOKEN, baseUrl: 'https://pidgey.example.com' });

    it('exposes no own property', () => {
      expect(Object.getOwnPropertyNames(client)).toEqual([]);
    });

    it('does not leak the token through inspect or JSON', () => {
      expect(inspect(client, { showHidden: true })).not.toContain(TOKEN);
      expect(JSON.stringify(client)).not.toContain(TOKEN);
    });
  });

  describe('send request', () => {
    const MESSAGE = { to: 'user@example.com', subject: 'Hello', text: 'Hi!', html: '<p>Hi!</p>' };
    let server;

    const clientFor = (extra = {}, baseUrl = server.baseUrl) => new PidgeyClient({ token: TOKEN, baseUrl, ...extra });

    beforeEach(async () => {
      server = await TestServer.start();
    });

    afterEach(() => server.stop());

    it('POSTs to /v1/messages', async () => {
      await clientFor().send(MESSAGE);

      expect(server.requests.length).toBe(1);
      expect(server.requests[0].method).toBe('POST');
      expect(server.requests[0].url).toBe('/v1/messages');
    });

    it('keeps a path prefix', async () => {
      await clientFor({}, `${server.baseUrl}/pidgey/`).send(MESSAGE);

      expect(server.requests[0].url).toBe('/pidgey/v1/messages');
    });

    it('sends the Authorization, Content-Type and Accept headers', async () => {
      await clientFor().send(MESSAGE);
      const { headers } = server.requests[0];

      expect(headers.authorization).toBe(`Bearer ${TOKEN}`);
      expect(headers['content-type']).toBe('application/json');
      expect(headers.accept).toBe('application/json');
    });

    it('does not send an Origin header', async () => {
      await clientFor().send(MESSAGE);

      expect(server.requests[0].headers.origin).toBeUndefined();
    });

    it('sends the whitelisted fields as JSON', async () => {
      await clientFor().send({ ...MESSAGE, from: 'x@y.z', extra: 1 });

      expect(JSON.parse(server.requests[0].body)).toEqual(MESSAGE);
    });

    it('omits undefined fields', async () => {
      await clientFor().send({ to: 'user@example.com', subject: undefined, text: 'Hi!' });

      expect(JSON.parse(server.requests[0].body)).toEqual({ to: 'user@example.com', text: 'Hi!' });
    });

    it('sends a generated UUID Idempotency-Key, new for every call', async () => {
      const client = clientFor();
      await client.send(MESSAGE);
      await client.send(MESSAGE);
      const [first, second] = server.requests.map((request) => request.headers['idempotency-key']);

      expect(first).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
      expect(second).toMatch(/^[0-9a-f-]{36}$/);
      expect(second).not.toBe(first);
    });

    it('sends the caller key as is', async () => {
      await clientFor().send(MESSAGE, { idempotencyKey: 'order-1234' });

      expect(server.requests[0].headers['idempotency-key']).toBe('order-1234');
    });

    it('leaves an idle keep-alive connection that does not hang the suite', async () => {
      const client = clientFor();
      await client.send(MESSAGE);
      await client.send(MESSAGE);

      expect(server.requests.length).toBe(2);
    });

    it('does not report the token in the request body', async () => {
      await clientFor().send(MESSAGE);

      expect(server.requests[0].body).not.toContain(TOKEN);
    });
  });
});
