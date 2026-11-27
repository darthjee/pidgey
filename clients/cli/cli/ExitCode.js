import { PidgeyError } from 'pidgey-client';
import { UsageError } from './Arguments.js';

/**
 * The exit codes of the `pidgey` command.
 * @type {{ OK: number, FAILURE: number, USAGE: number, INTERRUPTED: number }}
 */
export const EXIT = Object.freeze({ OK: 0, FAILURE: 1, USAGE: 2, INTERRUPTED: 130 });

/**
 * Maps a failure to the exit code.
 * - a usage error, or the library's `CLIENT_CONFIGURATION`, is `2`;
 * - an `ABORTED` error while the signal is aborted (SIGINT) is `130`;
 * - any other failure is `1`.
 * @param {unknown} error - What was thrown.
 * @param {AbortSignal} signal - The signal aborted on SIGINT.
 * @returns {number} The exit code.
 */
export function exitCodeFor(error, signal) {
  if (error instanceof UsageError) {
    return EXIT.USAGE;
  }
  if (error instanceof PidgeyError) {
    if (error.code === 'CLIENT_CONFIGURATION') {
      return EXIT.USAGE;
    }
    if (error.code === 'ABORTED' && signal.aborted) {
      return EXIT.INTERRUPTED;
    }
  }
  return EXIT.FAILURE;
}
