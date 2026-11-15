# Device Authorization: Approver Endpoints

Purpose: define the endpoints called by a logged-in device to list, authorize or deny the
device-authorization requests raised against its own username, and the per-request authorize
lockout.

Shared rules (state machine, guards, `201` success codes, input rules, error answers, expiry
boundary, clock, known limitations) are in [device-authorization.md](../device-authorization.md).
The table is `authorization_requests`
([organizations/data-model/authorization-requests.md](../../../../organizations/data-model/authorization-requests.md));
settings are in [architecture/configuration.md](../../../../architecture/configuration.md).

All three endpoints are **gated** ([authentication/gate.md](../../../authentication/gate.md)):
only an `approved` user with a valid session reaches them; anyone else, including a `pending`
user, gets the gate's uniform `401`. The **caller** is the user authenticated by the gate.
A request can only be acted on by the user it was raised for (`user_id = caller`).

## `POST /auth/authorization-requests/mine.json`

Gated. Lists the caller's own pending requests, so the approver UI ("Authorizations" page)
can show them. Empty body (it has no fields).

Success: **`201`**:

```json
{
  "requests": [
    {
      "uuid": "3f1e7a52-8c1b-4d0e-9a51-2b7f0c9d1e44",
      "requestIp": "203.0.113.9",
      "requestUserAgent": "Mozilla/5.0 …",
      "createdAt": "2026-10-25T12:00:00.000Z",
      "expiresAt": "2026-10-25T13:00:00.000Z"
    }
  ]
}
```

- Filter: `user_id = caller`, `status = 'open'`, `expires_at > now`. Rows still `open` in the
  table but expired (`now >= expires_at`) are **never** listed; `mine` does not flip them.
- Order: newest first (`created_at DESC`).
- No request: `{ "requests": [] }`.
- `username`, `poll_token_hash` and the lockout columns are never included.

## `POST /auth/authorization-requests/:uuid/authorize.json`

Gated. Approves a request raised against the caller's **own** username, after re-checking
the caller's password.

Request:

```json
{ "password": "s3cretpass" }
```

| Field | Rules |
| --- | --- |
| `password` | string, not empty (never trimmed; no max length) |

Success: **`201`**, `{ "authorized": true }`.

Every business rejection answers the **same** `400 BAD_REQUEST` —
`Unable to authorize this request`, whatever the reason (not found, malformed uuid, foreign,
not `open`, expired, locked, wrong password).

Steps, in order (after the gate and body validation), in **one transaction**:

1. **Lock the row:** find it by `uuid` with `SELECT … FOR UPDATE`. The row lock is held from
   here through the password verification to the increment or reset (see [Lockout](#lockout)).
2. Row not found (unknown or malformed uuid), `user_id ≠ caller`, `status ≠ open`, or
   **expired** (`now >= expires_at`) → `400`. No password check runs and nothing is counted.
3. An **expired lock** (`authorize_locked_until <= now`) is cleared first
   (`authorize_failed_attempts = 0`, `authorize_locked_until = null`), so the count restarts
   from zero.
4. **Always** run the bcrypt compare (`password_verify()`) once: against the caller's digest,
   or against the hard-coded `DUMMY_DIGEST` ([session.md](../session.md#post-authloginjson))
   if the caller's user row is missing (then the password is wrong).
5. If the row is **locked** (`authorize_locked_until > now`) → commit and answer `400`. The
   counter is **not** incremented, even with the right password.
6. **Wrong password** → increment `authorize_failed_attempts`; when the incremented value is
   **`>=`** `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS` (default `5`), set
   `authorize_locked_until = now + AUTHORIZATION_REQUEST_AUTHORIZE_LOCK_TTL` (default `300`
   seconds, 5 minutes). Commit (the increment is kept although the request fails) and answer
   `400`.
7. **Right password** → `status = approved`, `approved_by_user_id = caller`,
   `resolved_at = now`, and reset the counter (`authorize_failed_attempts = 0`,
   `authorize_locked_until = null`), with a conditional update
   (`WHERE uuid = ? AND status = 'open'`). Commit; `201 { "authorized": true }`.

| Case | Answer | Failed attempt |
| --- | --- | --- |
| Anonymous, invalid session, user not `approved` | gate `401` | no |
| Validation failure | `400 VALIDATION_FAILED` | no |
| Not found, malformed uuid, foreign, not `open`, expired | `400 BAD_REQUEST` | no (no password check) |
| Locked (any password) | `400 BAD_REQUEST` | no |
| Wrong password | `400 BAD_REQUEST` | yes |
| Success | `201` | counter reset |

### Lockout

The authorize lockout follows the same mechanics as the account-edit lockout
([account.md](../account.md#lockout)), stored on the **request row**
(`authorize_failed_attempts`, `authorize_locked_until`):

- **Row lock across check, verify and increment.** The request row is held with
  `SELECT … FOR UPDATE` from the status and expiry checks through the password verification
  to the increment or reset, so parallel attempts on one request run one after the other and
  cannot run more password checks than the limit allows.
- The threshold is checked with **`>=`** against the **incremented** value, never with `=`.
- While `authorize_locked_until > now`, every attempt answers `400` without counting.
- An expired lock is cleared before the attempt is evaluated: one failure after expiry does
  not re-lock immediately.
- A success resets the counter.
- The lockout is **per request row**, not per user: other requests of the same user are not
  locked. It does **not** interact with the account-edit lockout (`account_edit_lockouts`).

## `POST /auth/authorization-requests/:uuid/deny.json`

Gated. Denies a request raised against the caller's own username. Empty body (it has no
fields). No password is needed.

Success: **`201`**, `{ "denied": true }`. Sets `status = denied`, `resolved_at = now`, with
a conditional update (`WHERE uuid = ? AND user_id = caller AND status = 'open'`).

- Row not found (unknown or malformed uuid), `user_id ≠ caller`, or `status ≠ open` (the
  conditional update affects 0 rows) → `400 BAD_REQUEST` — `Unable to deny this request`.
- Expiry is **not** checked: an expired-but-still-`open` row can be denied.

| Case | Answer |
| --- | --- |
| Anonymous, invalid session, user not `approved` | gate `401` |
| Not found, malformed uuid, foreign, not `open` | `400 BAD_REQUEST` — `Unable to deny this request` |
| `open` (expired or not) and owned by the caller | `201` |

## Edge cases

- Authorize on an expired, already `approved`, `denied`, `logged` or foreign row: the uniform
  `400`, no password check counted.
- Authorize while locked with the right password: `400`, counter unchanged.
- Authorize after the lock expired: the counter restarts from zero.
- Authorize and deny racing on one `open` row: the conditional updates let only one win; the
  other answers its uniform `400`.
- Deny of an expired-but-`open` row: succeeds.
- `mine` never lists expired rows, even if still `open` in the table.
- A `pending` or `denied` user cannot reach any of these endpoints (gate `401`), so their
  requests can never be approved.

## Testable rules

The implementation must cover at least:

- the lock at exactly `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS` failures, and a counter
  already above the threshold still locking (`>=`, not `=`);
- `400` while locked even with the right password, counter unchanged;
- an expired lock cleared before evaluation (the count restarts from zero);
- **N parallel wrong-password attempts** (N greater than
  `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS`) on one request perform **at most
  `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS` failed password checks** before the lock;
- the `DUMMY_DIGEST` compare when the caller's user row is missing;
- every authorize rejection has the same status, code and message;
- the lockout is per request (another request of the same user is unaffected) and
  independent of the account-edit lockout;
- `mine` filters by caller, `status = open` and `expires_at > now`, newest first;
- deny succeeds on an expired-but-`open` row and fails on any other status.

## Pending decisions

None. (The verification code and the retention of rows are pending in
[device-authorization.md](../device-authorization.md#pending-decisions).)
