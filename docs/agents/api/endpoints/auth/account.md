# Account Endpoint

Purpose: define `PATCH /auth/account.json`, the caller's own account edit, and its
failed-attempt lockout.

Shared rules (guards, trimming, identifiers, password hashing) are in [auth.md](../auth.md).
The lockout is stored in `account_edit_lockouts`
([organizations/data-model/account-edit-lockouts.md](../../../organizations/data-model/account-edit-lockouts.md));
`ACCOUNT_EDIT_MAX_ATTEMPTS` and `ACCOUNT_EDIT_LOCK_TTL` are in
[architecture/configuration.md](../../../architecture/configuration.md). Errors use
[conventions/errors.md](../../conventions/errors.md).

## `PATCH /auth/account.json`

**Gated** ([authentication/gate.md](../../authentication/gate.md)): only an `approved` user
with a valid session reaches it; anyone else, including a `pending` user, gets the gate's
uniform `401`. Updates the caller's own username, email and/or password. The current
password is always required.

Request:

```json
{
  "currentPassword": "s3cretpass",
  "username": "bobby",
  "email": "bobby@example.com",
  "newPassword": "evenmoresecret"
}
```

| Field | Rules |
| --- | --- |
| `currentPassword` | **required**; string, not empty (never trimmed; no max length) |
| `username` | optional; string, not empty (after trimming), max 255 characters |
| `email` | optional; valid email (after trimming), max 255 characters |
| `newPassword` | optional; string, min 8 characters, max 72 bytes, no NUL byte (never trimmed) |

An optional field that is absent or `null` is "not present". A present but empty value is a
validation error (`400 VALIDATION_FAILED`).

Success: **`200`**, `{ "username": "bobby", "email": "bobby@example.com" }`, the caller's
values after the update.

Steps, in order (after the gate and body validation):

1. None of `username`, `email`, `newPassword` present → `400 BAD_REQUEST` —
   `At least one of username, email, or newPassword is required`. This is **not** a failed
   attempt.
2. Open a transaction and **lock the caller's lockout row** (see [Lockout](#lockout)): make
   sure the row exists, then `SELECT … FOR UPDATE` it. Steps 2 to 5 run while holding this
   row lock, so concurrent requests of the same user are serialized. An **expired** lock
   (`locked_until <= now`) is cleared first (`failed_attempts = 0`, `locked_until = null`).
   If `locked_until > now` → commit and answer `423 LOCKED` —
   `Account temporarily locked due to too many failed attempts`.
3. Load the caller's row. Missing → `401 UNAUTHORIZED` — `Invalid session`.
4. These checks run in this order, stop at the first failure, and each **counts as one failed
   attempt** when it fails (the increment is committed before the error is answered):
   1. `currentPassword` does not match (`password_verify()`) → `400 BAD_REQUEST` —
      `Invalid current password`;
   2. a new `username` that differs from the current one is used by **another** user →
      `409 USERNAME_TAKEN` — `Username already in use`;
   3. a new `email` that differs from the current one is used by **another** user →
      `409 EMAIL_TAKEN` — `Email already in use`.
5. Apply the changes (a `newPassword` is hashed, see
   [Password hashing](../auth.md#password-hashing)); when `email` or `newPassword` changes,
   mark every unused password-reset token of the caller as used (see
   [Other rules](#other-rules)); **reset** the lockout row (`failed_attempts = 0`,
   `locked_until = null`); commit. A unique-index violation on update (a concurrent change
   took the value) applies none of the changes, answers the same `409` and counts as a
   failed attempt.

| Case | Answer | Failed attempt |
| --- | --- | --- |
| Anonymous, invalid session, user not `approved` | gate `401` | no |
| Validation failure | `400 VALIDATION_FAILED` | no |
| Nothing to change | `400 BAD_REQUEST` | no |
| Locked | `423 LOCKED` | no |
| Caller row missing | `401 UNAUTHORIZED` — `Invalid session` | no |
| Wrong `currentPassword` | `400 BAD_REQUEST` — `Invalid current password` | yes |
| Username / email taken | `409 USERNAME_TAKEN` / `EMAIL_TAKEN` | yes |
| Success | `200` | counter reset |

### "Taken" checks

- A field is checked only when its new value **differs byte-wise** (in PHP) from the current
  one; an identical value skips the check and is not an update.
- The query **excludes the caller's own row** (`id <> caller`), and matches with the column
  collation (case- and accent-insensitive, see [auth.md](../auth.md#identifiers)). A case-only
  change (`Bob` → `bob`) is therefore allowed and never answers `USERNAME_TAKEN` against the
  caller.

### Other rules

- Account edit does **not** rehash the stored digest (only login does).
- A successful edit that changes the `email` or the password marks **every unused
  password-reset token** of the caller as used
  (`UPDATE … SET used_at = ? WHERE user_id = ? AND used_at IS NULL`), in the same
  transaction as the update: a reset link sent to the old address, or requested before the
  password change, can no longer take over the account. A username-only change leaves them
  untouched. Token rules are in [recovery.md](recovery.md#password-reset-tokens).
- **Known limitation:** changing the password here does **not** revoke sessions (unlike
  reset-password), including the caller's other sessions. This is a decision of the auth
  endpoints issue (#28), kept for parity with the Citadel contract the copied frontend speaks:
  the caller has just proven the current password from a valid session, and revoking would
  also log out the caller's own other devices. A user who suspects a compromise should use
  password recovery, whose reset revokes every session.
- A new email is **not verified**: it is stored as given.

## Lockout

One `account_edit_lockouts` row per user, upserted. `now` is bound from PHP (see
[sessions.md](../../authentication/sessions.md#clock)).

- **Row lock across check, verify and increment.** Every attempt that passes step 1 runs in
  one transaction that holds the caller's lockout row with `SELECT … FOR UPDATE` from the
  lock check (step 2) through the password verification and "taken" checks (step 4) to the
  increment or reset (step 5). The row is first created if missing with an **atomic upsert**
  (`INSERT … ON DUPLICATE KEY UPDATE` that leaves an existing row unchanged), so two first
  attempts racing to insert it are covered. Concurrent requests of the same user therefore
  run one after the other: none can pass the lock check while another has a failure still
  uncounted, so parallel requests cannot run more password checks than the limit. Requests
  of different users never wait for each other.
- Each failed attempt increments the counter with the **atomic upsert**
  (`INSERT … ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1`) while holding the
  row lock, and the increment is committed even though the request fails.
- The threshold is checked with **`>=`** against the **incremented** value (computed in the
  same statement, or re-read under the row lock after it), never with `=`: when
  `failed_attempts >= ACCOUNT_EDIT_MAX_ATTEMPTS` (default `5`),
  `locked_until = now + ACCOUNT_EDIT_LOCK_TTL` (default `300` seconds, 5 minutes).
- While `locked_until > now`, every call answers `423` (step 2), whatever the body.
- An expired lock is cleared before the attempt is evaluated (step 2), so one failure after
  expiry does not re-lock immediately: the count restarts from zero.
- A successful edit resets the row to `failed_attempts = 0`, `locked_until = null`.
- A password reset does **not** clear the lockout
  ([recovery.md](recovery.md#post-authreset-passwordjson)).

## Testable rules

The implementation must cover at least:

- the lock at exactly `ACCOUNT_EDIT_MAX_ATTEMPTS` failures, and a counter already above the
  threshold still locking (`>=`, not `=`);
- `423` while locked; an expired lock cleared before evaluation;
- concurrent failures counted without losing increments;
- **N parallel wrong-password requests** (N greater than `ACCOUNT_EDIT_MAX_ATTEMPTS`) for the
  same user perform **at most `ACCOUNT_EDIT_MAX_ATTEMPTS` password checks**
  (`password_verify()` calls); every other request answers `423`;
- a successful change of `email` or of the password marks every unused password-reset token
  of the caller as used (a previously issued token then answers
  `400 Invalid or expired token`); a username-only change leaves them valid;
- the "nothing to change" case not counted; a case-only username change allowed;
- the 72-byte boundary and the NUL-byte rejection (`400 VALIDATION_FAILED`) on
  `newPassword`.

## Pending decisions

None.
