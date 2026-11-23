# PidgeyError and response handling

Give `PidgeyError` its full field set, and build the response reader and mapper (`errors-and-retry.md`, sections `PidgeyError`, Response body).

- `lib/PidgeyError.js`: `constructor(message, { code, statusCode, details, smtp, possiblySent = false, body, bodyTruncated } = {})`.
  - Optional fields are set only when defined, so they are absent rather than `undefined` on no-response codes.
  - `body` and `bodyTruncated` are defined with `Object.defineProperty(..., { enumerable: false, writable: false, configurable: true })`, and only when a body exists.
  - Its JSDoc documents every field and that new client-local codes may be added.
- `lib/response.js` (new):
  - `readText(response, signal)`:
    - rejects at once when `Content-Length` is over 65536, without reading;
    - otherwise reads `response.body` chunk by chunk, counting bytes after decompression, and cancels the reader and fails over 65536;
    - decodes with `new TextDecoder('utf-8')` (replacement on, `stream: true`);
    - returns `{ text }`, or signals "too large" to the caller.
  - `redact(text, token)`:
    - builds the candidate list: the configured token and its HTTP-trimmed form (`/^[\t ]+|[\t ]+$/g`), deduplicated and sorted by length, longest first;
    - replaces every occurrence with `[redacted]`, using `split`/`join` (no regex built from the token).
  - `cut(text)`: at most 4096 **bytes** of UTF-8, without splitting a code point (encode, slice, then step back over continuation bytes, or iterate code points). Returns `{ body, truncated }`.
  - `mapResponse(response, text, token)`:
    1. redacts first;
    2. on a 2xx with JSON, returns the parsed value;
    3. on a 3xx → `INVALID_RESPONSE` with the status;
    4. on a non-2xx with the envelope `{ error: { code, message } }` → a `PidgeyError` copying `code`, `message`, `details` and `smtp`, with `statusCode` from the body's `statusCode` when it is a number, otherwise the HTTP status;
    5. otherwise → `INVALID_RESPONSE`.

    It always attaches `body`/`bodyTruncated` from the cut, redacted text. It sets `possiblySent` per the table: `true` for an `INVALID_RESPONSE` on a 2xx, `SMTP_TIMEOUT`, an unknown `SMTP_*` on 504, `SMTP_PROTOCOL_ERROR` and `IDEMPOTENCY_KEY_IN_USE`; `false` otherwise, including `IDEMPOTENCY_KEY_REUSED`.
- Specs `specs/lib/PidgeyError_spec.js` and `specs/lib/response_spec.js` cover:
  - the envelope mapping, with the fallback to the HTTP status;
  - `INVALID_RESPONSE` for a non-JSON body and for JSON without `error`;
  - the 64 KiB cap by `Content-Length` (no read) and by streamed bytes, including a gzip body that inflates past the cap;
  - the 4 KiB cut, `bodyTruncated`, and a multi-byte character at the boundary;
  - redaction:
    - a token straddling 4096;
    - the trimmed and configured forms, longest first;
    - reflected in `message`, `details` and `smtp.message`;
  - `body`/`bodyTruncated` absent from `JSON.stringify` and `util.inspect`, yet readable;
  - `body` absent on the no-response codes;
  - `possiblySent` per code.

## Files to Change
- `clients/nodejs/lib/PidgeyError.js` — full field set, non-enumerable `body`/`bodyTruncated`.
- `clients/nodejs/lib/response.js` — new: capped read, redaction, cut, mapping.
- `clients/nodejs/specs/lib/PidgeyError_spec.js` — rewrite for the new fields.
- `clients/nodejs/specs/lib/response_spec.js` — new.
