import { VERSION as LIBRARY_VERSION } from 'pidgey-client';
import { parseArguments, USAGE } from './Arguments.js';
import { VERSION } from './version.js';

/**
 * Runs the `pidgey` command.
 * @param {string[]} argv - The arguments (without the node binary and the script path).
 * @param {{ stdout: { write: (text: string) => unknown }, stderr: { write: (text: string) => unknown } }} streams
 *   The output streams.
 * @returns {number} The exit code: `0` on success, `2` on a usage error.
 */
export function run(argv, { stdout, stderr }) {
  let args;
  try {
    args = parseArguments(argv);
  } catch (error) {
    // parseArguments only throws UsageError, so every failure here is a usage mistake.
    stderr.write(`pidgey: ${/** @type {Error} */ (error).message}\n${USAGE}`);
    return 2;
  }

  if (args.help) {
    stdout.write(USAGE);
  } else {
    stdout.write(`pidgey-cli ${VERSION} (pidgey-client ${LIBRARY_VERSION})\n`);
  }
  return 0;
}
