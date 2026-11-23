import { spawn } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { VERSION as LIBRARY_VERSION } from 'pidgey-client';
import { USAGE } from '../../cli/Arguments.js';

const BIN = new URL('../../bin/pidgey.js', import.meta.url).pathname;
const CLI_VERSION = JSON.parse(
  readFileSync(new URL('../../package.json', import.meta.url), 'utf8'),
).version;

/**
 * Runs the real `bin/pidgey.js` in a child process, without a shell.
 * @param {string[]} args - The arguments.
 * @returns {Promise<{ code: number | null, stdout: string, stderr: string }>} The outcome.
 */
function pidgey(args) {
  return new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [BIN, ...args], { stdio: ['ignore', 'pipe', 'pipe'] });
    let stdout = '';
    let stderr = '';
    child.stdout.setEncoding('utf8').on('data', (chunk) => { stdout += chunk; });
    child.stderr.setEncoding('utf8').on('data', (chunk) => { stderr += chunk; });
    child.on('error', reject);
    child.on('close', (code) => resolve({ code, stdout, stderr }));
  });
}

describe('bin/pidgey.js', () => {
  it('prints the versions and exits 0 for --version', async () => {
    const result = await pidgey(['--version']);

    expect(result.code).toBe(0);
    expect(result.stdout).toBe(`pidgey-cli ${CLI_VERSION} (pidgey-client ${LIBRARY_VERSION})\n`);
    expect(result.stderr).toBe('');
  });

  it('prints the usage on stderr and exits 2 for an unknown flag', async () => {
    const result = await pidgey(['--bogus']);

    expect(result.code).toBe(2);
    expect(result.stdout).toBe('');
    expect(result.stderr).toContain(USAGE);
  });
});
