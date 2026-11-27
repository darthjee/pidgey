import { PidgeyClient } from '../../lib/PidgeyClient.js';
import { PidgeyError } from '../../lib/PidgeyError.js';
import {
  findMessages, integrationEnv, uniqueSubject, waitForMessage,
} from '../../../scripts/integration_support.mjs';

const RECIPIENT = 'integration@example.com';
const SPEC_TIMEOUT_MS = 20000;

// A throw here (PIDGEY_INTEGRATION=required with a missing variable) fails the run.
const env = integrationEnv();

describe('PidgeyClient#send against a real Pidgey', () => {
  const clientFor = (token) => new PidgeyClient({
    token, baseUrl: env.url, allowInsecureHttp: true, maxRetries: 0,
  });
  const messageFor = (subject, text = 'Hello from the integration spec.') => ({
    to: RECIPIENT, subject, text,
  });
  const failureOf = (promise) => promise.then(
    () => { throw new Error('expected a rejection'); },
    (error) => error,
  );
  const spec = (name, body) => it(name, async () => {
    if (env === null) {
      pending('no integration stack: set PIDGEY_INTEGRATION_URL');
      return;
    }
    await body();
  }, SPEC_TIMEOUT_MS);

  spec('sends a message that reaches the mail server', async () => {
    const subject = uniqueSubject('pidgey-client');

    const result = await clientFor(env.token).send(messageFor(subject));
    const message = await waitForMessage(env.mailpitUrl, subject);

    expect(result.replayed).toBe(false);
    expect(message.Subject).toBe(subject);
    expect(message.To.map((address) => address.Address)).toEqual([RECIPIENT]);
  });

  spec('replays a stored answer for the same idempotency key and body', async () => {
    const subject = uniqueSubject('pidgey-client');
    const idempotencyKey = `key-${subject}`.replaceAll(' ', '-');
    const client = clientFor(env.token);

    await client.send(messageFor(subject), { idempotencyKey });
    await waitForMessage(env.mailpitUrl, subject);
    const replay = await client.send(messageFor(subject), { idempotencyKey });

    expect(replay.replayed).toBe(true);
    expect(await findMessages(env.mailpitUrl, subject)).toHaveSize(1);
  });

  spec('rejects the same idempotency key with a different body', async () => {
    const subject = uniqueSubject('pidgey-client');
    const idempotencyKey = `key-${subject}`.replaceAll(' ', '-');
    const client = clientFor(env.token);

    await client.send(messageFor(subject), { idempotencyKey });
    const error = await failureOf(
      client.send(messageFor(subject, 'A different body.'), { idempotencyKey }),
    );

    expect(error).toBeInstanceOf(PidgeyError);
    expect(error.statusCode).toBe(422);
    expect(error.code).toBe('IDEMPOTENCY_KEY_REUSED');
  });

  spec('rejects a token whose organization has no SMTP connection', async () => {
    const error = await failureOf(
      clientFor(env.noConnectionToken).send(messageFor(uniqueSubject('pidgey-client'))),
    );

    expect(error).toBeInstanceOf(PidgeyError);
    expect(error.statusCode).toBe(409);
    expect(error.code).toBe('NO_SMTP_CONNECTION');
    expect(error.possiblySent).toBe(false);
  });

  spec('rejects a revoked token', async () => {
    const error = await failureOf(
      clientFor(env.revokedToken).send(messageFor(uniqueSubject('pidgey-client'))),
    );

    expect(error).toBeInstanceOf(PidgeyError);
    expect(error.statusCode).toBe(401);
    expect(error.code).toBe('UNAUTHORIZED');
  });
});
