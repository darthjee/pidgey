# Approver endpoints

Create `api/endpoints/auth/device-authorization/approver.md` with:

- `POST /auth/authorization-requests/mine.json`: gated, empty body, `201 { requests: [...] }`
  with `uuid`, `requestIp`, `requestUserAgent`, `createdAt`, `expiresAt`; filter
  `user_id = caller`, `status = open`, `expires_at > now`, newest first.
- `POST /auth/authorization-requests/{uuid}/authorize.json`: `password` non-empty string, no
  max, never trimmed; uniform `400 BAD_REQUEST — Unable to authorize this request`; the
  per-row lockout of decision 7 (`SELECT … FOR UPDATE`, compare always runs with
  `DUMMY_DIGEST` fallback, locked → `400` without increment, expired lock cleared first,
  increment committed with `>=` against `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS`, lock
  for `AUTHORIZATION_REQUEST_AUTHORIZE_LOCK_TTL`, success resets the counter and sets
  `approved`, `approved_by_user_id`, `resolved_at`). Independent of the account-edit lockout.
- `POST /auth/authorization-requests/{uuid}/deny.json`: gated, empty body,
  `201 { denied: true }`, uniform `400 BAD_REQUEST — Unable to deny this request`, expiry not
  checked.
- Edge cases and testable rules for these endpoints.

## Files to Change
- `docs/agents/specs/api/endpoints/auth/device-authorization/approver.md` — new.
