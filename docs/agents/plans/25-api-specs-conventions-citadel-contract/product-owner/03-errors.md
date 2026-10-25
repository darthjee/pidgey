# Error envelope, codes and statuses
Create `api/conventions/errors.md` from Citadel 2.3 plus the issue's unified-contract decision:

- Envelope: `{ "error": { "code", "message", "details"? , "smtp"? }, "statusCode", "timestamp" }`.
  `details` (array of strings, message = entries joined by `"; "`) only for validation;
  `smtp` (`{ "code": <int>, "message": <string> }`) only for SMTP send failures; credentials
  never included. Include both examples from the issue.
- Specific code when the endpoint sets one, otherwise the category code by status (table:
  400 `BAD_REQUEST`/`VALIDATION_FAILED`, 401 `UNAUTHORIZED`, 403 `FORBIDDEN`, 404 `NOT_FOUND`,
  409 `CONFLICT`, 423 `LOCKED`, 429 `TOO_MANY_REQUESTS` reserved, 500 `INTERNAL_ERROR`, other
  `HTTP_<status>`).
- Specific codes table: the Citadel ones (`USERNAME_TAKEN`, `EMAIL_TAKEN`, `USER_DENIED`,
  `USER_NOT_PENDING`, `CANNOT_DENY_SELF`) plus a pointer to `email/failures.md` for the send
  codes (`NO_SMTP_CONNECTION`, `SMTP_*`). Endpoint specs may add codes; keep this table the
  registry.
- Unexpected errors: `500 INTERNAL_ERROR`, real error logged, never sent. Body-parser errors
  (`400`/`413`/`415`) with category code and reason phrase. Unknown route: `404 NOT_FOUND`.
- Status codes table replacing the old one (no `422` for validation; `422` stays for business
  rules like `USER_NOT_PENDING`; `502`/`503`/`504` for SMTP failures).
- The rule: never answer `401` for a business rule — `401` only means missing/invalid/expired
  credentials, because the frontend refreshes and retries on `401`.
- Enumeration safety as a general principle (details live in the endpoint specs).

## Files to Change
- `docs/agents/specs/api/conventions/errors.md` — new.
