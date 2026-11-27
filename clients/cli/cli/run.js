import { VERSION as LIBRARY_VERSION } from 'pidgey-client';
import { parseArguments, USAGE } from './Arguments.js';
import { VERSION } from './version.js';

/**
 * The environment the CLI runs in, injected so specs can drive it in-process.
 * @typedef {object} RunIo
 * @property {Record<string, string | undefined>} env - The environment variables.
 * @property {{ isTTY?: boolean }} stdin - The standard input (a readable stream).
 * @property {{ write: (text: string) => unknown }} stdout - The standard output.
 * @property {{ write: (text: string) => unknown }} stderr - The standard error.
 * @property {AbortSignal} signal - Aborted on SIGINT.
 * @property {(options: object) => unknown} [createClient] - Builds the library client; defaults
 *   to `new PidgeyClient(options)`.
 * @property {string} [platform] - The platform; defaults to `process.platform`.
 */

/**
 * Runs the `pidgey` command.
 * @param {string[]} argv - The arguments (without the node binary and the script path).
 * @param {RunIo} io - The environment: variables, streams, abort signal and factories.
 * @returns {Promise<number>} The exit code: `0` on success, `2` on a usage error.
 */
export async function run(argv, { stdout, stderr }) {
  let args;
  try {
    args = parseArguments(argv);
  } catch (error) {
    // parseArguments only throws UsageError, so every failure here is a usage mistake.
    const err = /** @type {import('./Arguments.js').UsageError} */ (error);
    stderr.write(`pidgey: ${err.message}\n${err.showUsage ? USAGE : ''}`);
    return 2;
  }

  if (args.mode === 'help') {
    stdout.write(USAGE);
  } else if (args.mode === 'version') {
    stdout.write(`pidgey-cli ${VERSION} (pidgey-client ${LIBRARY_VERSION})\n`);
  } else {
    // The send path arrives in the next steps.
    stderr.write('pidgey: sending is not available yet\n');
    return 2;
  }
  return 0;
}
