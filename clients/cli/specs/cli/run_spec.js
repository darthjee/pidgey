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

  beforeEach(() => {
    stdout = memoryStream();
    stderr = memoryStream();
  });

  it('prints both versions for --version', () => {
    expect(run(['--version'], { stdout, stderr })).toBe(0);
    expect(stdout.text).toBe(`pidgey-cli ${CLI_VERSION} (pidgey-client ${LIBRARY_VERSION})\n`);
    expect(stderr.text).toBe('');
  });

  it('prints the usage for --help', () => {
    expect(run(['--help'], { stdout, stderr })).toBe(0);
    expect(stdout.text).toBe(USAGE);
    expect(stderr.text).toBe('');
  });

  it('prefers --help when both are given', () => {
    expect(run(['--version', '--help'], { stdout, stderr })).toBe(0);
    expect(stdout.text).toBe(USAGE);
  });

  it('reports a usage error on stderr with exit 2', () => {
    expect(run(['--bogus'], { stdout, stderr })).toBe(2);
    expect(stdout.text).toBe('');
    expect(stderr.text).toMatch(/^pidgey: .+\n/);
    expect(stderr.text.endsWith(USAGE)).toBeTrue();
  });

  it('reports no argument as a usage error', () => {
    expect(run([], { stdout, stderr })).toBe(2);
    expect(stdout.text).toBe('');
  });
});
