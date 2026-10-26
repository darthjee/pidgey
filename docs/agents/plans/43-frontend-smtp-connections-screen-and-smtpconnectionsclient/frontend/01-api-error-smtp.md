# ApiError carries error.smtp
The test-email failure UI must show the SMTP server reply, which the API sends as
`error.smtp: { code, message }` on `SMTP_*` failures. `buildApiError` currently passes only
`status`, `message`, `code` and `details` to `ApiError`, so the reply is lost.

- Add an optional fifth constructor argument `smtp` to `ApiError` and store it as
  `this.smtp` (`undefined` when absent); update the JSDoc.
- `buildApiError` passes `error.smtp`.
- Specs: `ApiError` keeps `smtp`; `ApiClient` puts `error.smtp` from a `502` body onto the
  thrown error, and leaves it `undefined` when the body has none. Existing callers are
  unaffected (the new argument is last and optional).

## Files to Change
- `frontend/assets/js/client/ApiError.js` — new optional `smtp` field.
- `frontend/assets/js/client/ApiClient.js` — `buildApiError` forwards `error.smtp`.
- `frontend/specs/assets/js/client/ApiErrorSpec.js` (create if missing) and
  `frontend/specs/assets/js/client/ApiClientSpec.js` — cover the new field.
