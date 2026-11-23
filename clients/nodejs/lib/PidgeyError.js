/**
 * Error raised by the Pidgey client library.
 *
 * Further fields (`status`, `possiblySent`, `smtp`) are added together with `send`.
 */
export class PidgeyError extends Error {
  /**
   * Builds a PidgeyError.
   * @param {string} message - Human readable description of the failure.
   * @param {object} [details] - Extra information about the failure.
   * @param {string} [details.code] - Stable error code, from the API envelope or client-local.
   */
  constructor(message, { code } = {}) {
    super(message);
    this.name = 'PidgeyError';
    this.code = code;
  }
}
