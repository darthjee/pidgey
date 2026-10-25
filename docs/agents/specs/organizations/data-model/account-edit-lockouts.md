# Account Edit Lockouts Table

Purpose: define the `account_edit_lockouts` table that limits failed account-edit attempts.

Editing one's own account requires the current password; repeated failures lock further
attempts for a while. The lockout rules are defined in
[api/endpoints/auth/account.md](../../api/endpoints/auth/account.md#lockout); the thresholds
(`ACCOUNT_EDIT_MAX_ATTEMPTS`, `ACCOUNT_EDIT_LOCK_TTL`) in
[architecture/configuration.md](../../architecture/configuration.md#password-recovery-and-account-edit).
Conventions are in [conventions.md](conventions.md).

## `account_edit_lockouts`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | BIGINT | no | auto | PK |
| `user_id` | BIGINT | no | | FK → `users.id`; **unique** (one row per user, upserted) |
| `failed_attempts` | INT | no | `0` | Consecutive failed account-edit attempts |
| `locked_until` | DATETIME | yes | null | Account edits are refused until then |
| `created_at` | DATETIME | no | now | |
| `updated_at` | DATETIME | no | now, on update | |

Indexes: unique `user_id`.

Each account-edit attempt locks the user's row (`SELECT … FOR UPDATE`) for the whole attempt,
so concurrent attempts of one user are serialized; the lock is set when `failed_attempts`
is **greater than or equal to** `ACCOUNT_EDIT_MAX_ATTEMPTS` (see
[account.md](../../api/endpoints/auth/account.md#lockout)).

## Pending decisions

None.
