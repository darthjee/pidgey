# Account Edit Lockouts Table

Purpose: define the `account_edit_lockouts` table that limits failed account-edit attempts.

Editing one's own account requires the current password; repeated failures lock further
attempts for a while. The thresholds and durations are defined in
[api/authentication.md](../../api/authentication.md) (#27) and the account endpoint specs.
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

## Pending decisions

None.
