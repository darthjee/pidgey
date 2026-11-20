# Retained response body and ABORTED correction

Replace the retained-body marker in `errors-and-retry.md` (line 34) with the decision, and
correct `possiblySent` for `ABORTED`.

- **`PidgeyError` fields table:** replace the "Response body" row with two rows:
  - `body`: the raw response text, cut to 4 KiB without splitting a character, token
    redacted, **non-enumerable**; absent when there was no HTTP response
    (`CLIENT_CONFIGURATION`, `ABORTED`, `NETWORK_ERROR`);
  - `bodyTruncated`: `true` when the text was cut; non-enumerable.
- **New subsection "Response body"** (replaces the marker):
  - **Read cap:** every response, 2xx included, is read up to 64 KiB, counted after
    `fetch`'s transparent decompression; a `Content-Length` above the cap is rejected
    without reading; a longer body aborts the read and becomes `INVALID_RESPONSE`
    (`possiblySent: true` on a 2xx).
  - **Decoding:** UTF-8, with replacement characters.
  - **Redaction:** every literal occurrence of the token is replaced with `[redacted]`
    before any cut; encoded forms are not searched.
  - **Logging:** `JSON.stringify`, `util.inspect` (without `showHidden`) and logger error
    serializers do not show `body`/`bodyTruncated`; the parsed fields stay enumerable.
  - **Why Pidgey's own bodies are safe:** `error.smtp.message` is already redacted,
    sanitized and capped (link `email/failures.md#echo-redaction`); `details` hold rule
    messages; the token is never echoed. The cap and the redaction guard against bodies
    that do not come from Pidgey (a proxy page, a header-echoing server at a wrong
    `baseUrl`).
  - **Rejected:** no caps; keeping only the parsed envelope; a custom `toJSON` /
    `util.inspect.custom`.
- **`INVALID_RESPONSE` row** of the client-local codes table: add "a body over 64 KiB".
- **`ABORTED`:**
  - Change the `possiblySent` cell of the `ABORTED` row to: `true` when the abort fires
    after a request was dispatched (`fetch` called) and before its response body was fully
    read, or when an earlier attempt was ambiguous; `false` otherwise (before any request,
    or during the backoff after only unambiguous failures).
  - Add an "abort during an attempt in flight" row to the Ambiguous failures table.
  - `ABORTED` stays never retried.
- **`library.md`, Abort section:** add one bullet pointing to the `possiblySent` rule.
- **`cli.md`, Output:** state that `body` is never printed. Check that the "possibly sent"
  notice also applies to an `ABORTED` (SIGINT, exit `130`) that reports
  `possiblySent: true`, and state it.
- **`testing.md`:**
  - Replace "the retained response body" in the `PidgeyError_spec.js` row with:
    - the 64 KiB read cap (by bytes after decompression, and by `Content-Length`);
    - the 4 KiB cut and `bodyTruncated`;
    - no split character;
    - token redaction, including a token straddling the 4 KiB boundary;
    - `body` absent from `JSON.stringify` and `util.inspect`;
    - `body` absent on no-response codes.
  - Extend the `RetryPolicy_spec.js` row with `possiblySent` for `ABORTED`: before any
    request, in flight (including while the body is being read), during the backoff after
    unambiguous failures, and after an earlier ambiguous attempt.

## Files to Change
- `docs/agents/specs/node-client/errors-and-retry.md` — fields table, Response body subsection, code rows, Ambiguous failures table, marker removed.
- `docs/agents/specs/node-client/library.md` — Abort bullet.
- `docs/agents/specs/node-client/cli.md` — Output bullets.
- `docs/agents/specs/node-client/testing.md` — `PidgeyError_spec.js` and `RetryPolicy_spec.js` rows.
