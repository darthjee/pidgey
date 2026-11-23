import { PidgeyError } from './PidgeyError.js';

/** Largest response body read, in bytes after decompression. */
export const MAX_RESPONSE_BYTES = 64 * 1024;

/** Largest `body` kept on a `PidgeyError`, in UTF-8 bytes. */
export const MAX_BODY_BYTES = 4 * 1024;

/** The text a redacted token is replaced with. */
export const REDACTED = '[redacted]';

/**
 * Result of reading a response: the decoded text, or a marker that it was over the cap.
 * @typedef {{ text: string, tooLarge?: undefined } | { tooLarge: true, text?: undefined }} ReadResult
 */

/**
 * Reads a response body as UTF-8 text, up to 64 KiB.
 *
 * The cap counts bytes after `fetch`'s transparent decompression, so it also guards against a
 * decompression bomb. A `Content-Length` over the cap is refused without reading. A longer
 * stream cancels the read. Invalid UTF-8 becomes replacement characters.
 * @param {Response} response - The `fetch` response.
 * @param {AbortSignal} [signal] - Signal that aborts the read; an aborted signal rejects with its reason.
 * @returns {Promise<ReadResult>} The text, or `{ tooLarge: true }`.
 */
export async function readText(response, signal) {
  const declared = Number(response.headers.get('content-length'));
  if (declared > MAX_RESPONSE_BYTES) {
    await response.body?.cancel();
    return { tooLarge: true };
  }
  if (!response.body) {
    return { text: '' };
  }
  const reader = response.body.getReader();
  const decoder = new TextDecoder('utf-8');
  let text = '';
  let total = 0;
  for (;;) {
    signal?.throwIfAborted();
    const { done, value } = await reader.read();
    if (done) {
      return { text: text + decoder.decode() };
    }
    total += value.byteLength;
    if (total > MAX_RESPONSE_BYTES) {
      await reader.cancel();
      return { tooLarge: true };
    }
    text += decoder.decode(value, { stream: true });
  }
}

/**
 * Replaces every occurrence of the token in a text with `[redacted]`.
 *
 * `fetch` strips leading and trailing HTTP whitespace from header values, so the token as
 * actually sent can be the trimmed form of the configured one: both are matched, the longer
 * first. Encoded forms (URL-encoded, base64) are not searched.
 * @param {string} text - The response text.
 * @param {string} token - The configured token.
 * @returns {string} The text without the token.
 */
export function redact(text, token) {
  const trimmed = token.replace(/^[\t\n\r ]+|[\t\n\r ]+$/g, '');
  const candidates = [...new Set([token, trimmed])]
    .filter(Boolean)
    .sort((a, b) => b.length - a.length);
  return candidates.reduce((result, candidate) => result.split(candidate).join(REDACTED), text);
}

/**
 * Cuts a text to at most 4096 UTF-8 bytes, without splitting a character.
 * @param {string} text - The (already redacted) text.
 * @returns {{ body: string, truncated: boolean }} The kept text and whether it was cut.
 */
export function cut(text) {
  const bytes = new TextEncoder().encode(text);
  if (bytes.length <= MAX_BODY_BYTES) {
    return { body: text, truncated: false };
  }
  let end = MAX_BODY_BYTES;
  // The text is valid UTF-8 (it was decoded with replacement), so this stops within 3 steps.
  while ((bytes[end] & 0xC0) === 0x80) {
    end -= 1;
  }
  return { body: new TextDecoder('utf-8').decode(bytes.subarray(0, end)), truncated: true };
}

/**
 * Parses JSON text, returning `undefined` when it is not JSON.
 * @param {string} text - The text to parse.
 * @returns {unknown} The parsed value, or `undefined`.
 */
function parseJson(text) {
  try {
    return JSON.parse(text);
  } catch {
    return undefined;
  }
}

/**
 * Tells whether a value is a non-null, non-array object.
 * @param {unknown} value - The value to check.
 * @returns {boolean} `true` for a plain-looking object.
 */
function isObject(value) {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Tells whether a failure response means the message may have been delivered.
 *
 * `true` for `SMTP_TIMEOUT`, any `SMTP_*` code on 504, `SMTP_PROTOCOL_ERROR` and
 * `IDEMPOTENCY_KEY_IN_USE`; `false` for everything else, including `IDEMPOTENCY_KEY_REUSED`.
 * @param {string} code - The envelope code.
 * @param {number} status - The HTTP status.
 * @returns {boolean} The `possiblySent` value.
 */
function isAmbiguousCode(code, status) {
  return code === 'SMTP_TIMEOUT'
    || code === 'SMTP_PROTOCOL_ERROR'
    || code === 'IDEMPOTENCY_KEY_IN_USE'
    || (code.startsWith('SMTP_') && status === 504);
}

/**
 * Reads the envelope `{ error: { code, message } }` of a parsed body.
 * @param {unknown} parsed - The parsed JSON body.
 * @returns {Record<string, any> | undefined} The `error` object, or `undefined` when the body has no envelope.
 */
function envelopeError(parsed) {
  const error = isObject(parsed) ? /** @type {Record<string, any>} */ (parsed).error : undefined;
  const valid = isObject(error) && typeof error.code === 'string' && typeof error.message === 'string';
  return valid ? error : undefined;
}

/**
 * Builds the `PidgeyError` of a non-2xx response.
 * @param {number} status - The HTTP status.
 * @param {unknown} parsed - The parsed (redacted) JSON body, or `undefined`.
 * @param {object} attach - The `statusCode`, `body` and `bodyTruncated` fields.
 * @returns {PidgeyError} The error to throw.
 */
function failureError(status, parsed, attach) {
  const error = envelopeError(parsed);
  if (status >= 300 && status < 400) {
    return new PidgeyError('The API answered a redirect, which is not followed.', {
      ...attach, code: 'INVALID_RESPONSE',
    });
  }
  if (!error) {
    return new PidgeyError('The API answered a response that is not a Pidgey error.', {
      ...attach, code: 'INVALID_RESPONSE',
    });
  }
  const bodyStatus = /** @type {Record<string, unknown>} */ (parsed).statusCode;
  return new PidgeyError(error.message, {
    ...attach,
    code: error.code,
    statusCode: typeof bodyStatus === 'number' ? bodyStatus : status,
    details: error.details,
    smtp: error.smtp,
    possiblySent: isAmbiguousCode(error.code, status),
  });
}

/**
 * Maps a response to its success value or throws the matching `PidgeyError`.
 *
 * - 2xx with a JSON object body: returns it, unchanged.
 * - 2xx otherwise (not JSON, or not an object): `INVALID_RESPONSE`, `possiblySent: true`.
 * - 3xx: `INVALID_RESPONSE` keeping the status.
 * - Other statuses with the envelope `{ error: { code, message } }`: the envelope's fields.
 * - Any other body: `INVALID_RESPONSE` keeping the HTTP status.
 *
 * The token is redacted from the text before it is parsed, so it is absent from every field
 * and from `body`, which keeps at most 4 KiB.
 * @param {Response} response - The `fetch` response.
 * @param {string} text - The response text, as read (not yet redacted).
 * @param {string} token - The configured token, to redact.
 * @returns {object} The parsed success body.
 * @throws {PidgeyError} For every failure.
 */
export function mapResponse(response, text, token) {
  const redacted = redact(text, token);
  const { body, truncated } = cut(redacted);
  const status = response.status;
  const parsed = parseJson(redacted);
  const attach = { body, bodyTruncated: truncated, statusCode: status };

  if (status < 200 || status >= 300) {
    throw failureError(status, parsed, attach);
  }
  if (!isObject(parsed)) {
    throw new PidgeyError('The API answered a success status with a body that is not a JSON object.', {
      ...attach, code: 'INVALID_RESPONSE', possiblySent: true,
    });
  }
  return /** @type {object} */ (parsed);
}
