import { PidgeyError } from './PidgeyError.js';

/** The v1 message fields sent to the API; any other key of the caller's object is dropped. */
export const MESSAGE_FIELDS = ['to', 'subject', 'text', 'html'];

/** The format of the server's `Idempotency-Key`: 1 to 255 printable ASCII characters. */
const IDEMPOTENCY_KEY_FORMAT = /^[\x21-\x7E]{1,255}$/;

/** Error codes, anywhere in the `cause` chain of a `fetch` failure, proving nothing was sent. */
const UNAMBIGUOUS_CODES = new Set([
  'ECONNREFUSED',
  'ENOTFOUND',
  'EAI_AGAIN',
  'EHOSTUNREACH',
  'ENETUNREACH',
  'DEPTH_ZERO_SELF_SIGNED_CERT',
  'UNABLE_TO_VERIFY_LEAF_SIGNATURE',
]);

/** TLS and certificate failures: `ERR_TLS_*`, `ERR_SSL_*`, `CERT_*` and `*_CERT_*`. */
const TLS_CODE = /^(?:ERR_TLS_|ERR_SSL_|CERT_)|_CERT_/;

/** Longest `cause` chain walked, as a guard against a cycle. */
const MAX_CAUSE_DEPTH = 8;

/**
 * Throws a `CLIENT_CONFIGURATION` error: a mistake in the arguments of `send`.
 * @param {string} reason - What is wrong; it never contains the token, the URL or the key.
 * @returns {never} Always throws.
 */
export function failArgument(reason) {
  throw new PidgeyError(reason, { code: 'CLIENT_CONFIGURATION', possiblySent: false });
}

/**
 * Tells whether an idempotency key has the server's format.
 * @param {unknown} key - The caller's key.
 * @returns {boolean} `true` for 1 to 255 characters, each in `0x21`-`0x7E`.
 */
export function isValidIdempotencyKey(key) {
  return typeof key === 'string' && IDEMPOTENCY_KEY_FORMAT.test(key);
}

/**
 * Builds the JSON request body from a whitelist of the v1 fields.
 *
 * Fields that are `undefined` are omitted. The values are not validated: the API is the only
 * validator.
 * @param {unknown} message - The caller's message.
 * @returns {string} The JSON text.
 * @throws {PidgeyError} `CLIENT_CONFIGURATION` when the message is not an object or cannot be
 *   serialized.
 */
export function buildBody(message) {
  if (typeof message !== 'object' || message === null || Array.isArray(message)) {
    failArgument('send expects the message as an object.');
  }
  const source = /** @type {Record<string, unknown>} */ (message);
  const picked = Object.fromEntries(
    MESSAGE_FIELDS.filter((field) => source[field] !== undefined).map((field) => [field, source[field]]),
  );
  try {
    return JSON.stringify(picked);
  } catch {
    return failArgument('The message cannot be serialized as JSON.');
  }
}

/**
 * Returns the first string `code` found along the `cause` chain of an error, outermost first.
 * @param {unknown} error - The value `fetch` rejected with.
 * @returns {string | undefined} The code, or `undefined` when there is none.
 */
function causeCode(error) {
  let current = error;
  for (let depth = 0; depth < MAX_CAUSE_DEPTH && typeof current === 'object' && current !== null; depth += 1) {
    const { code, cause } = /** @type {{ code?: unknown, cause?: unknown }} */ (current);
    if (typeof code === 'string') {
      return code;
    }
    current = cause;
  }
  return undefined;
}

/**
 * Tells whether a `fetch` failure proves that the request never reached Pidgey.
 *
 * `fetch` does not say whether the request was written, so the failure is classified from the
 * first `code` along its `cause` chain.
 *
 * - **Unambiguous** (`false` for `possiblySent`): `ECONNREFUSED`, `ENOTFOUND`, `EAI_AGAIN`,
 *   `EHOSTUNREACH`, `ENETUNREACH`, and TLS or certificate failures (`ERR_TLS_*`, `ERR_SSL_*`,
 *   `CERT_*`, `*_CERT_*`, `DEPTH_ZERO_SELF_SIGNED_CERT`, `UNABLE_TO_VERIFY_LEAF_SIGNATURE`).
 * - **Ambiguous**: everything else, including `ECONNRESET`, `UND_ERR_SOCKET`, a failure with no
 *   code, and the per-attempt timeout. This errs on the safe side.
 * @param {unknown} error - The value `fetch` rejected with.
 * @returns {boolean} `true` when nothing can have been sent.
 */
export function isUnambiguousNetworkError(error) {
  const code = causeCode(error);
  return code !== undefined && (UNAMBIGUOUS_CODES.has(code) || TLS_CODE.test(code));
}

/**
 * Builds the `INVALID_RESPONSE` error of a response body over the 64 KiB cap.
 * @param {number} status - The HTTP status of the response.
 * @returns {PidgeyError} The error; possibly sent only on a 2xx.
 */
export function tooLargeError(status) {
  return new PidgeyError('The API answered a response body over the 64 KiB limit.', {
    code: 'INVALID_RESPONSE',
    statusCode: status,
    possiblySent: status >= 200 && status < 300,
  });
}
