import { Readable } from 'node:stream';
import { PidgeyError } from 'pidgey-client';
import { EXIT, exitCodeFor } from '../../cli/ExitCode.js';
import { USAGE } from '../../cli/Arguments.js';
import { run } from '../../cli/run.js';

const TOKEN = 'pdg_secret_token_value';
const TEXT = 'the secret message text';
const ARGV = ['--to', 'a@example.com', '--subject', 'Hi', '--text', TEXT];

/**
 * Builds an in-memory writable stub.
 * @returns {{ text: string, write: (chunk: string) => void }} The stub.
 */
function memoryStream() {
  return { text: '', write(chunk) { this.text += chunk; } };
}

describe('exit codes and output', () => {
  let stdout;
  let stderr;
  let controller;
  let client;
  let io;

  beforeEach(() => {
    stdout = memoryStream();
    stderr = memoryStream();
    controller = new AbortController();
    client = { send: jasmine.createSpy('send') };
    io = {
      env: { PIDGEY_TOKEN: TOKEN, PIDGEY_URL: 'https://user:pass@pidgey.example.com' },
      stdin: { isTTY: true, destroy: () => undefined },
      stdout,
      stderr,
      signal: controller.signal,
      platform: 'linux',
      createClient: () => client,
    };
  });

  const rejectWith = (error) => client.send.and.rejectWith(error);

  describe('success', () => {
    const result = { status: 'sent', smtp: { code: 250, message: 'OK' }, replayed: false };

    beforeEach(() => client.send.and.resolveTo(result));

    it('exits 0 and prints nothing', async () => {
      expect(await run(ARGV, io)).toBe(0);
      expect(stdout.text).toBe('');
      expect(stderr.text).toBe('');
    });

    it('prints the result as one JSON line with --verbose', async () => {
      expect(await run([...ARGV, '--verbose'], io)).toBe(0);
      expect(stdout.text).toBe(`${JSON.stringify(result)}\n`);
      expect(stderr.text).toBe('');
    });

    it('prints the replayed flag as resolved', async () => {
      client.send.and.resolveTo({ ...result, replayed: true });
      await run([...ARGV, '--verbose'], io);

      expect(JSON.parse(stdout.text).replayed).toBeTrue();
    });
  });

  describe('a failed send', () => {
    it('exits 1 with the code and message', async () => {
      rejectWith(new PidgeyError('The recipient is invalid.', { code: 'VALIDATION_ERROR', statusCode: 422 }));

      expect(await run(ARGV, io)).toBe(1);
      expect(stdout.text).toBe('');
      expect(stderr.text).toBe('pidgey: VALIDATION_ERROR: The recipient is invalid.\n');
    });

    it('adds the SMTP reply and the possibly-sent notice', async () => {
      rejectWith(new PidgeyError('SMTP timed out.', {
        code: 'SMTP_TIMEOUT', statusCode: 504, smtp: { code: 421, message: 'Try later' }, possiblySent: true,
      }));

      expect(await run(ARGV, io)).toBe(1);
      expect(stderr.text).toBe([
        'pidgey: SMTP_TIMEOUT: SMTP timed out.',
        'pidgey: SMTP reply: 421 Try later',
        'pidgey: the message was possibly sent',
        '',
      ].join('\n'));
    });

    it('adds only the SMTP reply when the message was not possibly sent', async () => {
      rejectWith(new PidgeyError('Rejected.', { code: 'SMTP_REJECTED', smtp: { code: 550, message: 'No' } }));

      expect(await run(ARGV, io)).toBe(1);
      expect(stderr.text).toBe('pidgey: SMTP_REJECTED: Rejected.\npidgey: SMTP reply: 550 No\n');
    });

    it('adds only the notice when there is no SMTP reply', async () => {
      rejectWith(new PidgeyError('Socket closed.', { code: 'NETWORK_ERROR', possiblySent: true }));

      expect(await run(ARGV, io)).toBe(1);
      expect(stderr.text).toBe('pidgey: NETWORK_ERROR: Socket closed.\npidgey: the message was possibly sent\n');
    });

    it('never prints the token, the message, the URL userinfo or the body of the error', async () => {
      rejectWith(new PidgeyError('Failed.', {
        code: 'INVALID_RESPONSE', statusCode: 502, body: `echo ${TOKEN} ${TEXT}`, possiblySent: true,
      }));
      await run(ARGV, io);

      for (const secret of [TOKEN, TEXT, 'user:pass', 'echo ']) {
        expect(stderr.text).not.toContain(secret);
        expect(stdout.text).not.toContain(secret);
      }
    });

    it('treats an ABORTED that is not a SIGINT as a failure', async () => {
      rejectWith(new PidgeyError('The call was aborted.', { code: 'ABORTED' }));

      expect(await run(ARGV, io)).toBe(1);
    });

    it('exits 1 and names only the error for an unexpected error', async () => {
      client.send.and.rejectWith(new TypeError(`leaks ${TOKEN}`));

      expect(await run(ARGV, io)).toBe(1);
      expect(stderr.text).toBe('pidgey: unexpected error (TypeError)\n');
    });

    it('exits 1 for something thrown that is not an Error', async () => {
      client.send.and.rejectWith('just a string');

      expect(await run(ARGV, io)).toBe(1);
      expect(stderr.text).toBe('pidgey: unexpected error (unknown)\n');
    });
  });

  describe('usage errors', () => {
    it('exits 2 for CLIENT_CONFIGURATION from the constructor, without the usage', async () => {
      io.createClient = () => {
        throw new PidgeyError('Cleartext HTTP is not allowed.', { code: 'CLIENT_CONFIGURATION' });
      };

      expect(await run(ARGV, io)).toBe(2);
      expect(stderr.text).toBe('pidgey: CLIENT_CONFIGURATION: Cleartext HTTP is not allowed.\n');
    });

    it('exits 2 for CLIENT_CONFIGURATION from send', async () => {
      rejectWith(new PidgeyError('Invalid idempotency key.', { code: 'CLIENT_CONFIGURATION' }));

      expect(await run([...ARGV, '--idempotency-key', 'bad key\n'], io)).toBe(2);
      expect(stderr.text).not.toContain(USAGE);
    });

    it('exits 2 for a missing token, without the usage', async () => {
      delete io.env.PIDGEY_TOKEN;

      expect(await run(ARGV, io)).toBe(2);
      expect(stderr.text).toBe('pidgey: Missing token: pass --token-file or set PIDGEY_TOKEN\n');
      expect(client.send).not.toHaveBeenCalled();
    });

    it('exits 2 for a missing URL', async () => {
      delete io.env.PIDGEY_URL;

      expect(await run(ARGV, io)).toBe(2);
      expect(stderr.text).toContain('Missing URL');
    });

    it('exits 2 for a TTY stdin with no body flag, without the usage', async () => {
      expect(await run(['--to', 'a@example.com', '--subject', 'Hi'], io)).toBe(2);
      expect(stderr.text).toMatch(/^pidgey: No body/);
      expect(stderr.text).not.toContain(USAGE);
      expect(client.send).not.toHaveBeenCalled();
    });

    it('exits 2 for stdin over the cap and sends nothing', async () => {
      io.stdin = Readable.from([new Uint8Array(1048577)], { objectMode: false });
      io.stdin.isTTY = false;

      expect(await run(['--to', 'a@example.com', '--subject', 'Hi'], io)).toBe(2);
      expect(stderr.text).toContain('1 MiB');
      expect(client.send).not.toHaveBeenCalled();
    });

    it('exits 2 for an unknown flag, with the usage', async () => {
      expect(await run([...ARGV, '--token', TOKEN], io)).toBe(2);
      expect(stderr.text.endsWith(USAGE)).toBeTrue();
      expect(stderr.text).not.toContain(TOKEN);
    });
  });

  describe('SIGINT', () => {
    it('exits 130 for ABORTED after the signal fired, with the possibly-sent notice', async () => {
      client.send.and.callFake(async () => {
        controller.abort();
        throw new PidgeyError('The call was aborted.', { code: 'ABORTED', possiblySent: true });
      });

      expect(await run(ARGV, io)).toBe(130);
      expect(stderr.text).toBe(
        'pidgey: ABORTED: The call was aborted.\npidgey: the message was possibly sent\n',
      );
    });

    it('exits 130 without a notice when nothing may have been sent', async () => {
      client.send.and.callFake(async () => {
        controller.abort();
        throw new PidgeyError('The call was aborted.', { code: 'ABORTED' });
      });

      expect(await run(ARGV, io)).toBe(130);
      expect(stderr.text).toBe('pidgey: ABORTED: The call was aborted.\n');
    });

    it('passes the signal to send', async () => {
      client.send.and.resolveTo({});
      await run(ARGV, io);

      expect(client.send.calls.mostRecent().args[1].signal).toBe(controller.signal);
    });

    it('exits 130, printing nothing and sending nothing, when interrupted while reading stdin', async () => {
      const createClient = jasmine.createSpy('createClient');
      io.createClient = createClient;
      io.stdin = new Readable({ read: () => undefined });
      io.stdin.isTTY = false;
      const pending = run(['--to', 'a@example.com', '--subject', 'Hi'], io);
      setImmediate(() => controller.abort());

      expect(await pending).toBe(130);
      expect(stderr.text).toBe('');
      expect(createClient).not.toHaveBeenCalled();
    });
  });
});

describe('exitCodeFor', () => {
  const signal = new AbortController().signal;

  it('exposes the four codes', () => {
    expect(EXIT).toEqual({ OK: 0, FAILURE: 1, USAGE: 2, INTERRUPTED: 130 });
  });

  it('maps an unknown library code to 1', () => {
    expect(exitCodeFor(new PidgeyError('x', { code: 'SOMETHING_NEW' }), signal)).toBe(1);
  });

  it('maps a non-error to 1', () => {
    expect(exitCodeFor(undefined, signal)).toBe(1);
  });
});
