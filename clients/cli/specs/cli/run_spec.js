import { readFileSync } from 'node:fs';
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

  beforeEach(() => {
    stdout = memoryStream();
    stderr = memoryStream();
    io = {
      env: {},
      stdin: { isTTY: true },
      stdout,
      stderr,
      signal: new AbortController().signal,
    };
  });

  it('prints both versions for --version', async () => {
    expect(await run(['--version'], io)).toBe(0);
    expect(stdout.text).toBe(`pidgey-cli ${CLI_VERSION} (pidgey-client ${LIBRARY_VERSION})\n`);
    expect(stderr.text).toBe('');
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
    expect(stdout.text).toBe('');
    expect(stderr.text).toContain("pidgey: Missing required option '--to'");
    expect(stderr.text.endsWith(USAGE)).toBeTrue();
  });

  it('does not send yet', async () => {
    expect(await run(['--to', 'a@example.com', '--subject', 'Hi'], io)).toBe(2);
    expect(stderr.text).toBe('pidgey: sending is not available yet\n');
  });
});
