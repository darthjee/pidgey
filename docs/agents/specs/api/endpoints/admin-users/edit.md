# Admin User Edit Endpoint

Purpose: define `POST /admin/users/{id}/edit.json`, the admin edit of any user's username,
email and/or password.

Shared rules (guards, admin user item, `{id}` handling, trimming, operator log) are in
[admin-users.md](../admin-users.md). Password hashing and identifiers follow
[auth.md](../auth.md); the "taken" checks are those of the account edit
([auth/account.md](../auth/account.md#taken-checks)). Errors use
[conventions/errors.md](../../conventions/errors.md).

## `POST /admin/users/{id}/edit.json`

Updates the target user's username, email and/or password. There is **no current password**
and **no lockout**: the caller is an instance admin, trusted with every account (see the
trust level in [organizations/entities.md](../../../organizations/entities.md#admin)).

Request:

```json
{ "username": "bobby", "email": "bobby@example.com", "newPassword": "resetbyadmin" }
```

| Field | Rules |
| --- | --- |
| `username` | optional; string, not empty (after trimming), max 255 characters |
| `email` | optional; valid email (after trimming), max 255 characters |
| `newPassword` | optional; string, min 8 characters, max 72 bytes, no NUL byte (never trimmed) |

- An optional field that is absent or `null` is "not present". A present but invalid value
  (including an empty or whitespace-only `username`) is `400 VALIDATION_FAILED`.
- Unknown fields are dropped, including `isAdmin`, `status` and `organizationId`: edit
  **never** changes them.
- A `newPassword` is hashed as in [auth.md](../auth.md#password-hashing). A present
  `newPassword` is always a password change; it is not compared with the current one.

Success: **`201`**, `{ "user": <admin user item> }`, **reloaded** after the update (see
[admin user item](../admin-users.md#admin-user-item)).

Steps, in order (after the guards, `{id}` parsing and body validation):

1. None of `username`, `email`, `newPassword` present → `400 BAD_REQUEST` —
   `At least one of username, email, or newPassword is required`.
2. Load the target. Unknown → `404 NOT_FOUND` — `User not found`.
3. **"Taken" checks**, as in [account.md](../auth/account.md#taken-checks):
   1. a new `username` that differs from the target's current one is used by **another**
      user → `409 USERNAME_TAKEN` — `Username already in use`;
   2. a new `email` that differs from the target's current one is used by **another** user →
      `409 EMAIL_TAKEN` — `Email already in use`.
4. In **one transaction**:
   1. apply the changes;
   2. when the `email` or the password changes, mark **every unused password-reset token** of
      the target as used (`UPDATE … SET used_at = ? WHERE user_id = ? AND used_at IS NULL`),
      so a reset link sent to the old address, or issued before the change, can no longer
      take over the account;
   3. when the `email` or the password changes, **revoke every active refresh token** of the
      target (`revoked_at IS NULL`) with reason **`admin_edit`**
      ([sessions.md](../../authentication/sessions.md#revocation-reasons)).

   A unique-index violation on update (a concurrent change took the value) rolls back,
   applies **nothing**, and answers the same `409 USERNAME_TAKEN` / `EMAIL_TAKEN`.
5. Reload the target and answer `201`.

A **username-only** change neither invalidates reset tokens nor revokes sessions.

| Case | Answer |
| --- | --- |
| Validation failure | `400 VALIDATION_FAILED` |
| Nothing to change (none of the three fields present) | `400 BAD_REQUEST` — `At least one of username, email, or newPassword is required` |
| Unknown id | `404 NOT_FOUND` — `User not found` |
| Username / email used by another user (including a lost race) | `409 USERNAME_TAKEN` / `409 EMAIL_TAKEN` |
| Success | `201 { "user": … }` |

### "Taken" checks

- A field is checked only when its new value **differs byte-wise** (in PHP) from the
  target's current one; an identical value skips the check and is not an update.
- The query **excludes the target's own row** (`id <> target`) and matches with the column
  collation (case- and accent-insensitive, see [auth.md](../auth.md#identifiers)). A
  case-only change (`Bob` → `bob`) is therefore allowed.

### Session revocation (divergence from Citadel)

Revoking the target's sessions with `admin_edit` when the email or the password changes is a
**deliberate divergence** from Citadel, which does not revoke. It bounds the admin's indirect
access (see [organizations/entities.md](../../../organizations/entities.md#admin)): once an
admin resets the credentials, whoever held a session with the old ones is logged out on the
next request. The frontend is unaffected: the target's sessions simply answer the gate's
`401` and fall back to login.

### Self-edit

An admin may edit their **own** row through this endpoint, without the current password and
outside the account-edit lockout. This is an accepted consequence of the admin trust level.
An email or password change then also revokes the admin's own sessions, including the one
making the request (its next request answers `401`).

### Operator log

A successful edit writes one [operator log line](../admin-users.md#operator-log) with the
admin's id, the target's id, the action `edit` and the **names** of the changed fields
(`username`, `email`, `password`; none when every present value was identical). It never
includes the values.

## Edge cases

- Only `null` fields (e.g. `{ "username": null }`) → `400 BAD_REQUEST`
  ("at least one").
- An identical `username` or `email` → no "taken" check and no update of that field; with no
  other change, the answer is `201` with the unchanged item, and no token or session is
  touched.
- A case-only username change (`Bob` → `bob`) → allowed, no `USERNAME_TAKEN` against the
  target.
- `isAdmin`, `status` or `organizationId` sent → dropped; with no other field present, the
  answer is the "at least one" `400`.
- A whitespace-only `username` → `400 VALIDATION_FAILED` (`username should not be empty`).
- Editing a `denied` or `pending` user → allowed; the status is unchanged.

## Testable rules

The implementation must cover at least:

- the "at least one" case, including only `null` fields and only dropped fields;
- the "taken" checks: byte-wise comparison, own row excluded, case-only change allowed, and
  the unique-index race answering `409` with nothing applied;
- an email or password change marks every unused reset token of the target as used (a
  previously issued token then answers `400 Invalid or expired token`) and revokes every
  active session of the target with `admin_edit` (rejected by the gate on the next request);
- a username-only change leaves reset tokens and sessions untouched;
- `isAdmin`, `status` and `organizationId` are never changed;
- the 72-byte boundary and the NUL-byte rejection (`400 VALIDATION_FAILED`) on
  `newPassword`;
- the response item is reloaded after the update;
- one operator log line per successful edit, with the changed field names and no values.

## Pending decisions

None. The shared ones (notifying the old email, an audit table, forbidding a combined email
and password change) are in [admin-users.md](../admin-users.md#pending-decisions).
