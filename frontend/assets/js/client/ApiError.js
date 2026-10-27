/**
 * Error raised when an API request fails, carrying the response's HTTP status and the
 * backend's standard error body fields (`error.message`, `error.code`, `error.details` and,
 * on `SMTP_*` failures, `error.smtp`).
 */
export default class ApiError extends Error {
  /**
   * Create an API error.
   *
   * @param {number} status - HTTP status code of the failed response.
   * @param {string} message - Human-readable error message returned by the backend (or a
   *   generic fallback when the response carried none).
   * @param {string} [code] - Machine-readable error code (e.g. `USERNAME_TAKEN`,
   *   `VALIDATION_FAILED`); `undefined` when the response carried none.
   * @param {string[]} [details] - Full list of validation messages; only present on
   *   validation failures, `undefined` otherwise.
   * @param {{code: number, message: string}} [smtp] - The SMTP server's reply carried by
   *   `SMTP_*` failures (e.g. a test email); `undefined` when the response carried none.
   */
  constructor(status, message, code, details, smtp) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.details = details;
    this.smtp = smtp;
  }
}
