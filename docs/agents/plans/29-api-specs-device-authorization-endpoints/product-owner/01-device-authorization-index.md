# Device-authorization index

Create `api/endpoints/auth/device-authorization.md`, the index of the flow:

- Overview: requester (not logged in) asks to log in as `username`; approver (logged in as
  that user) confirms with its password; requester polls until it gets a session.
- Table linking `device-authorization/requester.md` and `device-authorization/approver.md`.
- State machine (diagram from the Citadel reference), every transition a conditional update
  (`WHERE uuid = ? AND status = <from>`), lazy expiry.
- Shared rules (decisions 4 and 5): guards (origin check on all, gate on mine/authorize/deny),
  `201` success codes, trimming (`username` trimmed, `password` and `pollToken` as sent),
  malformed `{uuid}` treated as not found, error answers and messages, expiry boundary
  `now >= expires_at`, PHP UTC clock bound as a parameter.
- Known limitations (decision 8) and Pending decisions (decision 9: verification code,
  retention handed to #31).

Then link it from the index files.

## Files to Change
- `docs/agents/specs/api/endpoints/auth/device-authorization.md` — new index.
- `docs/agents/specs/api/endpoints/auth.md` — add the row to the index table; replace
  "Device authorization (`/auth/authorization-requests*`) is specified separately (#29)."
- `docs/agents/specs/api/endpoints.md` — mention device authorization in the `auth.md` row.
