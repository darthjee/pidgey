import { spawn } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import {
  findMessages,
  integrationEnv,
  uniqueSubject,
  waitForMessage,
} from '../../../scripts/integration_support.mjs';

const BIN = new URL('../../bin/pidgey.js', import.meta.url).pathname;
const RECIPIENT = 'integration@example.com';
const SPEC_TIMEOUT_MS = 30000;

/**
 * Starts the real `bin/pidgey.js` in a child process, without a shell, with a minimal
 * environment, and waits for it to exit.
 * @param {string[]} args - The arguments.
 * @param {{ url: string, token: string }} options - The Pidgey URL and the token (handed over
 *   through the environment, never through argv).
 * @returns {Promise<{ code: number | null, stdout: string, stderr: string }>} The outcome.
 */
function run(args, { url, token }) {
  const child = spawn(process.execPath, [BIN, '--allow-insecure-http', ...args], {
    stdio: ['ignore', 'pipe', 'pipe'],
    env: { PATH: process.env.PATH, PIDGEY_URL: url, PIDGEY_TOKEN: token },
  });

  return new Promise((resolve, reject) => {
    let stdout = '';
    let stderr = '';
    child.stdout.setEncoding('utf8').on('data', (chunk) => { stdout += chunk; });
    child.stderr.setEncoding('utf8').on('data', (chunk) => { stderr += chunk; });
    child.on('error', reject);
    child.on('close', (code) => resolve({ code, stdout, stderr }));
  });
}

describe('pidgey against a real Pidgey (integration)', () => {
  const env = integrationEnv();
  let subject;

  const send = (token, { text = 'Hello from the integration spec', extra = [] } = {}) => run(
    ['--to', RECIPIENT, '--subject', subject, '--text', text, ...extra],
    { url: env.url, token },
  );

  const expectNoToken = (result, token) => {
    expect(result.stdout).not.toContain(token);
    expect(result.stderr).not.toContain(token);
  };

  beforeEach(() => {
    if (env === null) {
      pending('integration environment not configured (PIDGEY_INTEGRATION_URL unset)');
      return;
    }
    subject = uniqueSubject('pidgey-cli');
  });

  it('sends a message: exit 0, empty stdout, one Mailpit message', async () => {
    const result = await send(env.token);

    expect(result.code).toBe(0);
    expect(result.stdout).toBe('');
    expectNoToken(result, env.token);

    const message = await waitForMessage(env.mailpitUrl, subject);
    expect(message.Subject).toBe(subject);
    expect(message.To.map((address) => address.Address)).toEqual([RECIPIENT]);
  }, SPEC_TIMEOUT_MS);

  it('replays the same idempotency key and body without sending twice', async () => {
    const key = randomUUID();
    const first = await send(env.token, { extra: ['--idempotency-key', key] });
    await waitForMessage(env.mailpitUrl, subject);
    const replay = await send(env.token, { extra: ['--idempotency-key', key, '--verbose'] });

    expect(first.code).toBe(0);
    expect(replay.code).toBe(0);
    expect(JSON.parse(replay.stdout).replayed).toBe(true);
    expectNoToken(first, env.token);
    expectNoToken(replay, env.token);
    expect(await findMessages(env.mailpitUrl, subject)).toHaveSize(1);
  }, SPEC_TIMEOUT_MS);

  it('rejects the same idempotency key with a different body (IDEMPOTENCY_KEY_REUSED)', async () => {
    const key = randomUUID();
    const first = await send(env.token, { extra: ['--idempotency-key', key] });
    await waitForMessage(env.mailpitUrl, subject);
    const reused = await send(env.token, { text: 'A different body', extra: ['--idempotency-key', key] });

    expect(first.code).toBe(0);
    expect(reused.code).toBe(1);
    expect(reused.stdout).toBe('');
    expect(reused.stderr).toContain('IDEMPOTENCY_KEY_REUSED');
    expectNoToken(reused, env.token);
    expect(await findMessages(env.mailpitUrl, subject)).toHaveSize(1);
  }, SPEC_TIMEOUT_MS);

  it('exits 1 with NO_SMTP_CONNECTION for an organization without a connection', async () => {
    const result = await send(env.noConnectionToken);

    expect(result.code).toBe(1);
    expect(result.stdout).toBe('');
    expect(result.stderr).toContain('NO_SMTP_CONNECTION');
    expectNoToken(result, env.noConnectionToken);
  }, SPEC_TIMEOUT_MS);

  it('exits 1 with UNAUTHORIZED for a revoked token', async () => {
    const result = await send(env.revokedToken);

    expect(result.code).toBe(1);
    expect(result.stdout).toBe('');
    expect(result.stderr).toContain('UNAUTHORIZED');
    expectNoToken(result, env.revokedToken);
  }, SPEC_TIMEOUT_MS);
});
