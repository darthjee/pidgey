import { readFileSync } from 'node:fs';
import { chmod, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { Readable } from 'node:stream';
import { VERSION as LIBRARY_VERSION } from 'pidgey-client';
import { USAGE } from '../../cli/Arguments.js';
import { run } from '../../cli/run.js';

const CLI_VERSION = JSON.parse(
  readFileSync(new URL('../../package.json', import.meta.url), 'utf8'),
).version;

/**
 * Builds an in-memory writable stub.
 * @returns {{ text: string, write: (chunk: string) => void }} The stub.
 */
function memoryStream() {
  return {
    text: '',
    write(chunk) {
      this.text += chunk;
    },
  };
}

describe('run', () => {
  let stdout;
  let stderr;
  let io;
  let client;

  beforeEach(() => {
    stdout = memoryStream();
    stderr = memoryStream();
    client = { send: jasmine.createSpy('send').and.resolveTo({ status: 'sent', smtp: {}, replayed: false }) };
    io = {
      env: { PIDGEY_TOKEN: 'tok', PIDGEY_URL: 'https://pidgey.example.com' },
      stdin: { isTTY: true, destroy: jasmine.createSpy('destroy') },
      stdout,
      stderr,
      signal: new AbortController().signal,
      createClient: jasmine.createSpy('createClient').and.returnValue(client),
    };
  });

  it('prints both versions for --version', async () => {
    expect(await run(['--version'], io)).toBe(0);
    expect(stdout.text).toBe(`pidgey-cli ${CLI_VERSION} (pidgey-client ${LIBRARY_VERSION})\n`);
    expect(stderr.text).toBe('');
    expect(io.createClient).not.toHaveBeenCalled();
  });

  it('prints the usage for --help', async () => {
    expect(await run(['--help'], io)).toBe(0);
    expect(stdout.text).toBe(USAGE);
    expect(stderr.text).toBe('');
  });

  it('prefers --help when both are given', async () => {
    expect(await run(['--version', '--help'], io)).toBe(0);
    expect(stdout.text).toBe(USAGE);
  });

  it('reports a parse error on stderr with the usage and exit 2', async () => {
    expect(await run(['--bogus'], io)).toBe(2);
    expect(stdout.text).toBe('');
    expect(stderr.text).toMatch(/^pidgey: .+\n/);
    expect(stderr.text.endsWith(USAGE)).toBeTrue();
  });

  it('reports a missing required flag as a parse error', async () => {
    expect(await run([], io)).toBe(2);
    expect(stderr.text).toContain("pidgey: Missing required option '--to'");
    expect(stderr.text.endsWith(USAGE)).toBeTrue();
  });

  it('reports other usage errors without the usage', async () => {
    io.env = {};

    expect(await run(['--to', 'a@example.com', '--subject', 'Hi', '--text', 'x'], io)).toBe(2);
    expect(stderr.text).toBe('pidgey: Missing token: pass --token-file or set PIDGEY_TOKEN\n');
  });

  it('builds the client from the credentials and the flags', async () => {
    const argv = ['--to', 'a@example.com', '--subject', 'Hi', '--text', 'x', '--retry-ambiguous', '--allow-insecure-http'];

    expect(await run(argv, io)).toBe(0);
    expect(io.createClient).toHaveBeenCalledOnceWith({
      token: 'tok',
      baseUrl: 'https://pidgey.example.com',
      retryAmbiguous: true,
      allowInsecureHttp: true,
    });
  });

  it('prefers --url over PIDGEY_URL', async () => {
    await run(['--to', 'a@example.com', '--subject', 'Hi', '--text', 'x', '--url', 'https://flag.example.com'], io);

    expect(io.createClient.calls.mostRecent().args[0].baseUrl).toBe('https://flag.example.com');
  });

  it('sends the message parts and the signal', async () => {
    await run(['--to', 'a@example.com', '--subject', 'Hi', '--text', 'plain', '--html', '<p>rich</p>'], io);

    expect(client.send).toHaveBeenCalledOnceWith(
      { to: 'a@example.com', subject: 'Hi', text: 'plain', html: '<p>rich</p>' },
      { signal: io.signal },
    );
  });

  it('forwards --idempotency-key as given', async () => {
    await run(['--to', 'a@example.com', '--subject', 'Hi', '--text', 'x', '--idempotency-key', 'my key'], io);

    expect(client.send.calls.mostRecent().args[1]).toEqual({ signal: io.signal, idempotencyKey: 'my key' });
  });

  it('sends only the html part with --html alone', async () => {
    await run(['--to', 'a@example.com', '--subject', 'Hi', '--html', '<p>x</p>'], io);

    expect(client.send.calls.mostRecent().args[0]).toEqual({
      to: 'a@example.com', subject: 'Hi', html: '<p>x</p>',
    });
  });

  it('sends stdin as the text when no body flag is given', async () => {
    io.stdin = Readable.from([new TextEncoder().encode('from stdin')], { objectMode: false });
    io.stdin.isTTY = false;
    await run(['--to', 'a@example.com', '--subject', 'Hi'], io);

    expect(client.send.calls.mostRecent().args[0]).toEqual({
      to: 'a@example.com', subject: 'Hi', text: 'from stdin',
    });
  });

  it('builds a real client by default, which refuses an invalid URL with exit 2', async () => {
    delete io.createClient;

    const code = await run(['--to', 'a@example.com', '--subject', 'Hi', '--text', 'x', '--url', 'not a url'], io);

    expect(code).toBe(2);
    expect(stderr.text).toMatch(/^pidgey: CLIENT_CONFIGURATION: /);
  });

  it('uses the process platform by default for the mode warning', async () => {
    const dir = await mkdtemp(join(tmpdir(), 'pidgey-cli-'));
    try {
      const tokenFile = join(dir, 'token');
      await writeFile(tokenFile, 'tok', { mode: 0o644 });
      await chmod(tokenFile, 0o644);
      await run(['--to', 'a@example.com', '--subject', 'Hi', '--text', 'x', '--token-file', tokenFile], io);

      expect(stderr.text.includes('warning')).toBe(process.platform !== 'win32');
    } finally {
      await rm(dir, { recursive: true, force: true });
    }
  });
});
