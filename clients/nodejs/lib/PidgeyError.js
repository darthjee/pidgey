/**
 * Error raised by the Pidgey client library.
 *
 * Further fields (`statusCode`, `details`, `smtp`, `body`) are added together with `send`.
 */
export class PidgeyError extends Error {
  /**
   * Builds a PidgeyError.
   * @param {string} message - Human readable description of the failure.
   * @param {object} [details] - Extra information about the failure.
   * @param {string} [details.code] - Stable error code, from the API envelope or client-local.
   * @param {boolean} [details.possiblySent] - Whether the message may have been delivered.
   */
  constructor(message, { code, possiblySent = false } = {}) {
    super(message);
    this.name = 'PidgeyError';
    this.code = code;
    this.possiblySent = possiblySent;
  }
}
