# Session Endpoints

Purpose: define the contracts of register, login, refresh, logoff and status, the endpoints
that start, continue, end and inspect a session.

Shared rules (guards, trimming, identifiers, password hashing) are in
[auth.md](../auth.md). Tokens, sessions, rotation and replay detection are defined in
[authentication/sessions.md](../../authentication/sessions.md); the cookie in
[authentication/access-token.md](../../authentication/access-token.md#cookie); the shared
`user` payload in [authentication/gate.md](../../authentication/gate.md#shared-user-payload).
Errors use the envelope and codes of [conventions/errors.md](../../conventions/errors.md);
validation messages follow [conventions/validation.md](../../conventions/validation.md).

## `POST /auth/register.json`

Public. Creates an organization together with its first user, and logs the user in right
away.

Request:

```json
{
  "username": "bob",
  "email": "bob@example.com",
  "password": "s3cretpass",
  "organizationName": "Bob's Shop"
}
```

| Field | Rules |
| --- | --- |
| `username` | string, not empty (after trimming), max 255 characters |
| `email` | valid email (after trimming), max 255 characters |
| `password` | string, min 8 characters, max 72 bytes, no NUL byte (never trimmed) |
| `organizationName` | **required**; string, not empty (after trimming), max 255 characters |

Every field error is reported at once (e.g.
`organizationName must be shorter than or equal to 255 characters`). Organization names are
**not unique**: two organizations may share a name, and no "name taken" error exists.

Success: **`201`**, the [shared `user` payload](../../authentication/gate.md#shared-user-payload),
and the `access_token` cookie is set. The response does not include the organization; it is
read through [`GET /organization.json`](../pidgey-admin/organization.md#get-organizationjson).

Side effects, in order:

1. **Pre-check** that `username` and `email` are free, with one query matching either. If a
   row matches, answer `409 USERNAME_TAKEN` when its username matches (checked first),
   otherwise `409 EMAIL_TAKEN`. Denied users' rows are kept, so their username and email stay
   taken.
2. Hash the password (see [Password hashing](../auth.md#password-hashing)).
3. In **one transaction**:
   1. insert the organization with `name = organizationName`;
   2. insert the user with that `organization_id`, `is_admin = false`, and `status`:
      `approved` only when `USER_APPROVAL_REQUIRED` is exactly `false`, otherwise `pending`
      (see [architecture/configuration.md](../../../architecture/configuration.md));
   3. start a new session (insert the first refresh token, see
      [sessions.md](../../authentication/sessions.md#sessions)).
4. If the user insert hits a unique-index violation (a concurrent register took the name
   between the pre-check and the insert), the **whole transaction is rolled back**,
   organization included, and the answer is the same `409`: `USERNAME_TAKEN` when the
   username is taken (checked first), otherwise `EMAIL_TAKEN`.

The response is the same for `pending` and `approved` users; a `pending` user's session is
blocked by the gate until an admin approves them.

| Case | Answer |
| --- | --- |
| Validation failure | `400 VALIDATION_FAILED` |
| Username taken (pre-check or unique index) | `409 USERNAME_TAKEN` — `username is not available` |
| Email taken (pre-check or unique index) | `409 EMAIL_TAKEN` — `email is not available` |
| Success | `201` |

No application-level throttle applies in v1 (see [Brute force](#brute-force)).

### Known limitation: enumeration

Register reveals whether a username or email already exists, denied users included:
uniqueness inherently reveals existence, and hiding only some users is not possible without
hiding everyone. Register is the **documented exception** to
[enumeration safety](../../conventions/errors.md#enumeration-safety). A generic `409` was
rejected: it still reveals that something is taken and is worse for users. An
email-verification flow would allow real enumeration safety (pending decision in
[auth.md](../auth.md#pending-decisions)).

## `POST /auth/login.json`

Public. Logs in by `username` (never by email).

Request:

```json
{ "username": "bob", "password": "s3cretpass" }
```

| Field | Rules |
| --- | --- |
| `username` | string, not empty (after trimming) |
| `password` | string, not empty (never trimmed; no max length) |

Success: **`201`**, the [shared `user` payload](../../authentication/gate.md#shared-user-payload),
and the `access_token` cookie is set. A **new session** starts.

| Case | Answer |
| --- | --- |
| Validation failure | `400 VALIDATION_FAILED` |
| Unknown username, or wrong password (any status) | `401 UNAUTHORIZED` — `Invalid username or password` |
| Correct password, user `denied` | `403 USER_DENIED` — `This account has been denied access`; no tokens, no cookie |
| Correct password, user `pending` | `201`, the same as approved (the gate blocks the session until approval) |
| Correct password, user `approved` | `201` |

Timing and enumeration rules:

- The password is **always** checked with `password_verify()`, even when the username is
  unknown: then it is verified against `DUMMY_DIGEST`, so the response time does not reveal
  whether the username exists.
- `DUMMY_DIGEST` is a **hard-coded** `PASSWORD_DEFAULT` hash of a random throwaway string,
  not computed per request. A unit test asserts that
  `password_needs_rehash(DUMMY_DIGEST)` is false, so a change of PHP's default algorithm or
  cost fails the test instead of silently making unknown-user logins faster than real ones.
- The user status is checked only **after** the password, so it cannot be probed without
  the password.

**Rehash on login:** after a successful `password_verify()` against a real user's digest, if
`password_needs_rehash()` is true, a fresh `password_hash(PASSWORD_DEFAULT)` digest is stored.
No other endpoint rehashes.

### Brute force

There is **no application-level throttle** on login or register in v1 (Citadel parity). A
per-username lockout was rejected: it lets anyone lock a victim out, and a "locked" answer
reveals that the username exists. Per-IP throttling is a pending decision in
[api/pending.md](../../pending.md); until then, rate limiting `/auth/*.json` at the proxy is
recommended. The current production host has no configurable proxy, so this is a known gap
(see [deployment/known-gaps.md](../../../../deployment/known-gaps.md#no-proxy-rate-limiting)).

## `POST /auth/refresh.json`

Public. Rotates the refresh token and continues the session.

Request:

```json
{ "refreshToken": "9f2c…" }
```

| Field | Rules |
| --- | --- |
| `refreshToken` | string, not empty |

Success: **`201`**, the [shared `user` payload](../../authentication/gate.md#shared-user-payload)
with the **new** refresh token, and a new `access_token` cookie is set (same `sid`).

Checks, in order (rotation, replay detection and the conditional revoke are defined in
[sessions.md](../../authentication/sessions.md#rotation)):

| # | Case | Answer |
| --- | --- | --- |
| 0 | Missing or empty `refreshToken` | `400 VALIDATION_FAILED` |
| 1 | Hash not found, or `expires_at <= now` | `401 UNAUTHORIZED` — `Invalid or expired refresh token` |
| 2 | Revoked with `rotated` | [Replay detection](../../authentication/sessions.md#replay-detection): revoke every active token of the user with `replay_detected`, then `401` — `Invalid or expired refresh token` |
| 3 | Revoked with any other reason | `401` — `Invalid or expired refresh token` |
| 4 | User row missing | `401` — `Invalid or expired refresh token` |
| 5 | User `denied` | Revoke the presented token with `admin_denied`, then `401` — `Invalid or expired refresh token` (no replay detection) |
| 6 | User `pending` or `approved` | Rotate atomically; `201`. Losing a concurrent rotation (conditional revoke affects 0 rows) → `401` — `Invalid or expired refresh token`, no side effect |

Every `401` here uses the code `UNAUTHORIZED` and the **same message**, `Invalid or expired
refresh token`, so the answer does not reveal which check failed; the frontend then sends the
user to log in.

## `DELETE /auth/logoff.json`

Public. Ends the whole session of the presented token.

Request (JSON **body** on `DELETE`, see
[conventions/format.md](../../conventions/format.md#content-type)):

```json
{ "refreshToken": "9f2c…" }
```

| Field | Rules |
| --- | --- |
| `refreshToken` | string, not empty |

Success: **`204 No Content`**, empty body, and the `access_token` cookie is cleared
([access-token.md](../../authentication/access-token.md#cookie)).

Side effects:

- Look up the token by hash, **whatever its state**: an expired, rotated or otherwise revoked
  token still identifies its session.
- Revoke every **active** row with the same `user_id` and `session_uuid` with reason
  `logout`. The user's other sessions are untouched.
- An unknown token is a **silent no-op** that still answers `204` (and clears the cookie).
- The behaviour is the same for every user status.

| Case | Answer |
| --- | --- |
| Missing or empty `refreshToken` | `400 VALIDATION_FAILED` |
| Anything else | `204` |

## `POST /auth/status.json`

Public. A **passive** "am I logged in?" check, called when the frontend mounts. It never
rotates, never runs replay detection and never sets or clears the cookie, so a second tab's
check cannot log out the first tab.

Request:

```json
{ "refreshToken": "9f2c…" }
```

| Field | Rules |
| --- | --- |
| `refreshToken` | string, not empty |

Success: **always `201`** when the body is valid (`400 VALIDATION_FAILED` otherwise).

| Caller | Response body | Side effect |
| --- | --- | --- |
| Active token, user `approved` | `{ "loggedIn": true, "isAdmin": true }` (or `false`) | none |
| Active token, user `pending` | `{ "loggedIn": false, "isAdmin": false, "status": "pending" }` | none (session kept) |
| Active token, user `denied` | `{ "loggedIn": false, "isAdmin": false }` | revoke the presented token with `admin_denied` |
| Unknown, revoked or expired token, or user row missing | `{ "loggedIn": false, "isAdmin": false }` | none |

- "Active" means the hash was found, `revoked_at IS NULL` and `expires_at > now`.
- `isAdmin` is read from the database row.
- The denied answer is byte for byte the anonymous one, so status cannot tell a denied user
  from an unknown token.
- The pending answer is the only `loggedIn: false` answer for which the frontend keeps the
  session and shows a "waiting for approval" state.

## Pending decisions

None. (Per-IP throttling is tracked in [api/pending.md](../../pending.md); the username
character set and email verification in [auth.md](../auth.md#pending-decisions).)
