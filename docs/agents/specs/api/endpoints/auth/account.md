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
| `newPassword` | optional; string, min 8 characters, max 72 bytes (never trimmed) |

An optional field that is absent or `null` is "not present". A present but empty value is a
validation error (`400 VALIDATION_FAILED`).

Success: **`200`**, `{ "username": "bobby", "email": "bobby@example.com" }`, the caller's
values after the update.

Steps, in order (after the gate and body validation):

1. None of `username`, `email`, `newPassword` present → `400 BAD_REQUEST` —
   `At least one of username, email, or newPassword is required`. This is **not** a failed
   attempt.
2. Read the caller's lockout row. An **expired** lock (`locked_until <= now`) is cleared
   first (`failed_attempts = 0`, `locked_until = null`). If `locked_until > now` →
   `423 LOCKED` — `Account temporarily locked due to too many failed attempts`.
3. Load the caller's row. Missing → `401 UNAUTHORIZED` — `Invalid session`.
4. These checks run in this order, stop at the first failure, and each **counts as one failed
   attempt** when it fails:
   1. `currentPassword` does not match (`password_verify()`) → `400 BAD_REQUEST` —
      `Invalid current password`;
   2. a new `username` that differs from the current one is used by **another** user →
      `409 USERNAME_TAKEN` — `Username already in use`;
   3. a new `email` that differs from the current one is used by **another** user →
      `409 EMAIL_TAKEN` — `Email already in use`.
5. Apply the changes (a `newPassword` is hashed, see
   [Password hashing](../auth.md#password-hashing)) and **reset** the lockout row
   (`failed_attempts = 0`, `locked_until = null`). A unique-index violation on update (a
   concurrent change took the value) answers the same `409` and counts as a failed attempt.

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
- Changing the password here does **not** revoke sessions (unlike reset-password).
- A new email is **not verified**: it is stored as given.

## Lockout

One `account_edit_lockouts` row per user, upserted. `now` is bound from PHP (see
[sessions.md](../../authentication/sessions.md#clock)).

- Each failed attempt increments the counter with an **atomic upsert**
  (`INSERT … ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1`), which also
  covers two first failures racing to insert the row.
- When `failed_attempts` reaches `ACCOUNT_EDIT_MAX_ATTEMPTS` (default `5`),
  `locked_until = now + ACCOUNT_EDIT_LOCK_TTL` (default `300` seconds, 5 minutes).
- While `locked_until > now`, every call answers `423` (step 2), whatever the body.
- An expired lock is cleared before the attempt is evaluated (step 2), so one failure after
  expiry does not re-lock immediately: the count restarts from zero.
- A successful edit resets the row to `failed_attempts = 0`, `locked_until = null`.
- A password reset does **not** clear the lockout
  ([recovery.md](recovery.md#post-authreset-passwordjson)).

## Testable rules

The implementation must cover at least: the lock at exactly `ACCOUNT_EDIT_MAX_ATTEMPTS`
failures; `423` while locked; an expired lock cleared before evaluation; concurrent failures
counted without losing increments; the "nothing to change" case not counted; a case-only
username change allowed; the 72-byte boundary on `newPassword`.

## Pending decisions

None.
