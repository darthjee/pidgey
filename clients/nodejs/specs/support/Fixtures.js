/**
 * Valid `/v1` response bodies shared by the specs, each with its HTTP status.
 *
 * The contract spec validates every fixture of {@link CONTRACT_FIXTURES} against the OpenAPI
 * document, so a spec built on these replies cannot drift from the API. A body that is
 * off-contract on purpose (an extra field, a missing `smtp`, an unknown shape, non-JSON text)
 * stays inline in the spec that needs it, named as such.
 */

/** The `200` success body of `POST /v1/messages`. */
export const SUCCESS_BODY = { status: 'sent', smtp: { code: 250, message: 'OK: queued as 1a2b3c' } };

/** The timestamp the error envelopes carry: ISO 8601 UTC with milliseconds. */
export const TIMESTAMP = '2026-10-25T12:00:00.000Z';

/** The scripted `200` reply of `TestServer`, with its status. */
export const SUCCESS_REPLY = { status: 200, json: SUCCESS_BODY };

/**
 * Builds the scripted reply of one error envelope.
 * @param {string} code - The `error.code`.
 * @param {number} statusCode - The HTTP status, repeated in the body.
 * @param {object} [fields] - The other `error` fields: `details`, `smtp`, or a `message`
 *   (default `<code> happened`).
 * @returns {{ status: number, json: object }} The `TestServer` reply.
 */
export function errorReply(code, statusCode, { message = `${code} happened`, ...fields } = {}) {
  return {
    status: statusCode,
    json: { error: { code, message, ...fields }, statusCode, timestamp: TIMESTAMP },
  };
}

/** The `[code, status]` pairs of the error envelopes the specs script. */
export const ERROR_CASES = [
  ['VALIDATION_FAILED', 400],
  ['BAD_REQUEST', 400],
  ['UNAUTHORIZED', 401],
  ['FORBIDDEN', 403],
  ['NO_SMTP_CONNECTION', 409],
  ['IDEMPOTENCY_KEY_IN_USE', 409],
  ['IDEMPOTENCY_KEY_REUSED', 422],
  ['UNPROCESSABLE', 422],
  ['TOO_MANY_REQUESTS', 429],
  ['INTERNAL_SERVER_ERROR', 500],
  ['SMTP_REJECTED', 502],
  ['SMTP_AUTH_FAILED', 502],
  ['SMTP_TLS_FAILED', 502],
  ['SMTP_PROTOCOL_ERROR', 502],
  ['SMTP_CONNECTION_FAILED', 502],
  ['SMTP_TEMPORARY_FAILURE', 503],
  ['SMTP_TIMEOUT', 504],
  // Codes the document does not list: the client must tolerate them.
  ['SMTP_NEW_THING', 502],
  ['SMTP_NEW_THING', 503],
  ['SMTP_NEW_THING', 504],
];

/**
 * Every fixture the contract spec validates: the success reply, each error case, and the
 * envelopes carrying `details` and `smtp`.
 * @type {{ name: string, reply: { status: number, json: object } }[]}
 */
export const CONTRACT_FIXTURES = [
  { name: 'success', reply: SUCCESS_REPLY },
  ...ERROR_CASES.map(([code, status]) => ({ name: `${code} ${status}`, reply: errorReply(code, status) })),
  {
    name: 'VALIDATION_FAILED 400 with details',
    reply: errorReply('VALIDATION_FAILED', 400, { message: 'to must be an email', details: ['to must be an email'] }),
  },
  {
    name: 'SMTP_TIMEOUT 504 with smtp',
    reply: errorReply('SMTP_TIMEOUT', 504, { message: 'SMTP timeout', smtp: { code: 421, message: 'slow' } }),
  },
];
