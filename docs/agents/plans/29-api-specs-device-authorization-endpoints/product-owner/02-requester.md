# Requester endpoints

Create `api/endpoints/auth/device-authorization/requester.md` with:

- `POST /auth/authorization-requests.json`: request/validation table, `201` payload
  (`uuid`, `pollToken` 48 random bytes hex, `expiresAt`), behaviour steps (user lookup, both
  rate-limit counts always run with `>=` against `AUTHORIZATION_REQUEST_CREATE_LIMIT` within
  `AUTHORIZATION_REQUEST_CREATE_WINDOW`, over-limit returns the payload without saving, open
  cap with conditional eviction of the oldest `open` row by `created_at` then `id`, insert
  with `user_id` null for unknown usernames and `poll_token_hash = sha256(pollToken)`),
  enumeration safety, IP and user agent via `api/conventions/client-ip.md`.
- `POST /auth/authorization-requests/{uuid}/poll.json`: lookup by uuid and token hash, the
  answer table (`404` indistinguishable, lazy expiry flip, status echo), and the claim
  (decision 6): one transaction for the conditional `approved → logged`, user status check,
  `logged → denied` flip or session insert; rollback on session-insert failure; only the
  winning poll gets the shared `user` payload, `refreshToken` and the `access_token` cookie.
- Edge cases relevant to these endpoints and their testable rules (from the issue's Edge
  cases and Testing sections).

## Files to Change
- `docs/agents/specs/api/endpoints/auth/device-authorization/requester.md` — new.
