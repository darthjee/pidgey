import { PidgeyError } from 'pidgey-client';
import { UsageError, USAGE } from './Arguments.js';

/**
 * Formats the `--verbose` line printed after a successful send.
 * @param {object} result - What `send` resolved with.
 * @returns {string} One JSON line.
 */
export function formatResult(result) {
  return `${JSON.stringify(result)}\n`;
}

/**
 * Formats a failure for stderr. Never includes the message content, the token, the URL or the
 * response body of the error.
 * - a `PidgeyError`: its code and message, the SMTP reply when present, and a notice when the
 *   message was possibly sent;
 * - a `UsageError`: its message, followed by the usage text for a parse error;
 * - anything else: only the name of the error.
 * @param {unknown} error - What was thrown.
 * @returns {string} The text for stderr, ending in a newline.
 */
export function formatFailure(error) {
  if (error instanceof PidgeyError) {
    return formatPidgeyError(error);
  }
  if (error instanceof UsageError) {
    return `pidgey: ${error.message}\n${error.showUsage ? USAGE : ''}`;
  }
  return `pidgey: unexpected error (${error instanceof Error ? error.name : 'unknown'})\n`;
}

/**
 * Formats a `PidgeyError`.
 * @param {PidgeyError} error - The error.
 * @returns {string} The lines for stderr.
 */
function formatPidgeyError(error) {
  let text = `pidgey: ${error.code}: ${error.message}\n`;
  if (error.smtp) {
    text += `pidgey: SMTP reply: ${error.smtp.code} ${error.smtp.message}\n`;
  }
  if (error.possiblySent) {
    text += 'pidgey: the message was possibly sent\n';
  }
  return text;
}
