import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { VERSION as LIBRARY_VERSION } from 'pidgey-client';
import { USAGE } from '../../cli/Arguments.js';
import { TestServer } from '../support/TestServer.js';

const BIN = new URL('../../bin/pidgey.js', import.meta.url).pathname;
const CLI_VERSION = JSON.parse(
  readFileSync(new URL('../../package.json', import.meta.url), 'utf8'),
).version;
const TOKEN = 'pdg_subprocess_secret';
const BASE_ARGS = ['--to', 'a@example.com', '--subject', 'Hello'];
const IN_FLIGHT_TIMEOUT_MS = 10000;

/**
 * Starts the real `bin/pidgey.js` in a child process, without a shell, with a minimal
 * environment. NODE_V8_COVERAGE is passed on so c8 counts the child.
 * @param {string[]} args - The arguments.
 * @param {{ env?: object, stdin?: Buffer | string | null }} [options] - Extra environment
 *   variables, and what to write to the child's stdin before closing it (`null`: leave it open).
 * @returns {{ child: object, done: Promise<{ code: number | null, stdout: string, stderr: string }> }}
 *   The child and its outcome.
 */
function start(args, { env = {}, stdin = '' } = {}) {
  const child = spawn(process.execPath, [BIN, ...args], {
    stdio: ['pipe', 'pipe', 'pipe'],
    env: {
      PATH: process.env.PATH,
      ...(process.env.NODE_V8_COVERAGE ? { NODE_V8_COVERAGE: process.env.NODE_V8_COVERAGE } : {}),
      ...env,
    },
  });
  // The child may exit before reading everything (the stdin cap): a broken pipe is expected.
  child.stdin.on('error', () => undefined);
  if (stdin !== null) {
    child.stdin.end(stdin);
  }

  const done = new Promise((resolve, reject) => {
    let stdout = '';
    let stderr = '';
    child.stdout.setEncoding('utf8').on('data', (chunk) => { stdout += chunk; });
    child.stderr.setEncoding('utf8').on('data', (chunk) => { stderr += chunk; });
    child.on('error', reject);
    child.on('close', (code) => resolve({ code, stdout, stderr }));
  });
  return { child, done };
}

describe('bin/pidgey.js', () => {
  let server;

  const sendWith = (args, options = {}) => start(args, {
    ...options,
    env: { PIDGEY_TOKEN: TOKEN, PIDGEY_URL: server.baseUrl, ...options.env },
  });

  afterEach(async () => {
    await server?.stop();
    server = undefined;
  });

  describe('without a server', () => {
    it('prints the versions and exits 0 for --version', async () => {
      const result = await start(['--version']).done;

      expect(result.code).toBe(0);
      expect(result.stdout).toBe(`pidgey-cli ${CLI_VERSION} (pidgey-client ${LIBRARY_VERSION})\n`);
      expect(result.stderr).toBe('');
    });

    it('prints the usage on stderr and exits 2 for an unknown flag', async () => {
      const result = await start(['--bogus']).done;

      expect(result.code).toBe(2);
      expect(result.stdout).toBe('');
      expect(result.stderr).toContain(USAGE);
    });

    it('exits 2 for a malformed --url (CLIENT_CONFIGURATION), without leaking the token', async () => {
      const result = await start([...BASE_ARGS, '--text', 'x', '--url', 'not a url'], {
        env: { PIDGEY_TOKEN: TOKEN },
      }).done;

      expect(result.code).toBe(2);
      expect(result.stdout).toBe('');
      expect(result.stderr).toMatch(/^pidgey: CLIENT_CONFIGURATION: /);
      expect(result.stderr).not.toContain(TOKEN);
    });

    it('exits 2 for a cleartext non-loopback URL, without echoing the userinfo', async () => {
      const result = await start([...BASE_ARGS, '--text', 'x', '--url', 'http://user:hunter2@pidgey.invalid'], {
        env: { PIDGEY_TOKEN: TOKEN },
      }).done;

      expect(result.code).toBe(2);
      expect(result.stderr).toMatch(/^pidgey: CLIENT_CONFIGURATION: /);
      expect(result.stderr).not.toContain('hunter2');
      expect(result.stderr).not.toContain(TOKEN);
    });

    it('exits 2 for a missing token', async () => {
      const result = await start([...BASE_ARGS, '--text', 'x'], {
        env: { PIDGEY_URL: 'http://127.0.0.1:1' },
      }).done;

      expect(result.code).toBe(2);
      expect(result.stderr).toContain('Missing token');
    });
  });

  describe('against the server', () => {
    it('exits 0 with empty stdout and stderr, sending the message', async () => {
      server = await TestServer.start();
      const result = await sendWith([...BASE_ARGS, '--text', 'Hi!']).done;

      expect(result.code).toBe(0);
      expect(result.stdout).toBe('');
      expect(result.stderr).toBe('');
      expect(server.requests.length).toBe(1);
      expect(server.requests[0].method).toBe('POST');
      expect(server.requests[0].url).toBe('/v1/messages');
      expect(server.requests[0].headers.authorization).toContain(TOKEN);
      expect(JSON.parse(server.requests[0].body)).toEqual({ to: 'a@example.com', subject: 'Hello', text: 'Hi!' });
    });

    it('prints the result with --verbose', async () => {
      server = await TestServer.start();
      const result = await sendWith([...BASE_ARGS, '--text', 'Hi!', '--verbose']).done;

      expect(result.code).toBe(0);
      expect(JSON.parse(result.stdout)).toEqual({ status: 'sent', smtp: { code: 250, message: 'OK' }, replayed: false });
    });

    it('exits 1 with the error on stderr', async () => {
      server = await TestServer.start({
        status: 502,
        json: { error: { code: 'SMTP_PERMANENT_FAILURE', message: 'The mail server refused the message.' } },
      });
      const result = await sendWith([...BASE_ARGS, '--text', 'Hi!']).done;

      expect(result.code).toBe(1);
      expect(result.stdout).toBe('');
      expect(result.stderr).toContain('pidgey: SMTP_PERMANENT_FAILURE: The mail server refused the message.');
      expect(result.stderr).not.toContain(TOKEN);
    });

    it('sends a body piped through stdin', async () => {
      server = await TestServer.start();
      const result = await sendWith(BASE_ARGS, { stdin: 'Hello from the pipe\n' }).done;

      expect(result.code).toBe(0);
      expect(JSON.parse(server.requests[0].body).text).toBe('Hello from the pipe\n');
    });

    it('sends an empty stdin as an empty text', async () => {
      server = await TestServer.start();
      const result = await sendWith(BASE_ARGS, { stdin: '' }).done;

      expect(result.code).toBe(0);
      expect(JSON.parse(server.requests[0].body).text).toBe('');
    });

    it('copes with a stdin closed before the CLI reads it', async () => {
      server = await TestServer.start();
      const { child, done } = sendWith(BASE_ARGS, { stdin: null });
      child.stdin.destroy();
      const result = await done;

      expect(result.code).toBe(0);
      expect(JSON.parse(server.requests[0].body).text).toBe('');
    });

    it('exits 2 and sends nothing for more than 1 MiB through a pipe', async () => {
      server = await TestServer.start();
      const result = await sendWith(BASE_ARGS, { stdin: Buffer.alloc(2 * 1048576, 0x61) }).done;

      expect(result.code).toBe(2);
      expect(result.stdout).toBe('');
      expect(result.stderr).toContain('1 MiB');
      expect(server.requests.length).toBe(0);
    });

    it('exits 130 on SIGINT while the request is in flight, closing the connection', async () => {
      server = await TestServer.start({ hold: true });
      const { child, done } = sendWith([...BASE_ARGS, '--text', 'Hi!']);
      await server.firstRequest;
      child.kill('SIGINT');
      const result = await done;
      await server.clientClosed;

      expect(result.code).toBe(130);
      expect(result.stdout).toBe('');
      expect(result.stderr).toContain('pidgey: ABORTED: ');
      expect(result.stderr).toContain('pidgey: the message was possibly sent');
    }, IN_FLIGHT_TIMEOUT_MS);

    it('exits promptly after a send, leaving the idle keep-alive socket alone', async () => {
      server = await TestServer.start();
      const startedAt = Date.now();
      const { child, done } = sendWith([...BASE_ARGS, '--text', 'Hi!']);
      const timer = setTimeout(() => child.kill('SIGKILL'), 5000);
      const result = await done;
      clearTimeout(timer);

      expect(result.code).toBe(0);
      expect(Date.now() - startedAt).toBeLessThan(5000);
    }, IN_FLIGHT_TIMEOUT_MS);
  });
});
