/**
 * Error raised by the Pidgey client library: the single class of every failure of `send`
 * and of every configuration mistake.
 *
 * `code` is either the `error.code` of the API envelope or a client-local code:
 * `CLIENT_CONFIGURATION`, `NETWORK_ERROR`, `INVALID_RESPONSE` or `ABORTED`. New client-local
 * codes may be added in a minor release, so consumers must tolerate unknown codes.
 *
 * `code`, `message`, `statusCode`, `details`, `smtp` and `possiblySent` are enumerable. `body`
 * and `bodyTruncated` are not, so they stay out of `JSON.stringify`, `util.inspect` and logger
 * serializers; they are still readable on purpose (`error.body`).
 */
export class PidgeyError extends Error {
  /**
   * Builds a PidgeyError. The optional fields are set only when given, so they are absent
   * (not `undefined`) on the failures that have none.
   * @param {string} message - Human readable description of the failure.
   * @param {object} [fields] - The other fields of the error.
   * @param {string} [fields.code] - Stable error code, from the API envelope or client-local.
   * @param {number} [fields.statusCode] - The `statusCode` of the envelope, or the HTTP status.
   *   Absent when there is no HTTP response.
   * @param {string[]} [fields.details] - `error.details` of the envelope (validation errors).
   * @param {{ code: number, message: string }} [fields.smtp] - The SMTP server reply, when present.
   * @param {boolean} [fields.possiblySent] - Whether the message may have been delivered
   *   (default `false`).
   * @param {string} [fields.body] - The response text, with the token redacted and cut to 4 KiB.
   *   Stored as a non-enumerable property.
   * @param {boolean} [fields.bodyTruncated] - `true` when `body` was cut. Non-enumerable.
   */
  constructor(message, {
    code, statusCode, details, smtp, possiblySent = false, body, bodyTruncated,
  } = {}) {
    super(message);
    this.name = 'PidgeyError';
    this.code = code;
    this.possiblySent = possiblySent;
    if (statusCode !== undefined) {
      this.statusCode = statusCode;
    }
    if (details !== undefined) {
      this.details = details;
    }
    if (smtp !== undefined) {
      this.smtp = smtp;
    }
    if (body !== undefined) {
      /** @type {string | undefined} */
      this.body;
      /** @type {boolean | undefined} */
      this.bodyTruncated;
      Object.defineProperty(this, 'body', hidden(body));
      Object.defineProperty(this, 'bodyTruncated', hidden(bodyTruncated === true));
    }
  }
}

/**
 * Builds the descriptor of a non-enumerable, read-only property.
 * @param {unknown} value - The property value.
 * @returns {PropertyDescriptor} The descriptor.
 */
function hidden(value) {
  return { value, enumerable: false, writable: false, configurable: true };
}
