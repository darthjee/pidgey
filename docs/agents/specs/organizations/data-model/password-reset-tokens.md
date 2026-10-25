# Password Reset Tokens Table

Purpose: define the `password_reset_tokens` table used for account recovery.

Each row is one recovery token (from a recovery link or email). The recovery flow is
defined in [api/endpoints/auth/recovery.md](../../api/endpoints/auth/recovery.md); the token
lifetime (`PASSWORD_RESET_TOKEN_TTL`) and the recover cooldown (`PASSWORD_RESET_COOLDOWN`) in
[architecture/configuration.md](../../architecture/configuration.md#password-recovery-and-account-edit). Conventions are in [conventions.md](conventions.md).

## `password_reset_tokens`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | BIGINT | no | auto | PK |
| `token_hash` | CHAR(64) | no | | SHA-256 hex of the reset token; **unique** |
| `user_id` | BIGINT | no | | FK → `users.id`; indexed. Account being recovered |
| `created_at` | DATETIME | no | now | |
| `expires_at` | DATETIME | no | | End of the token's lifetime (configurable) |
| `used_at` | DATETIME | yes | null | Set on a successful reset (on the consumed token and on every other unused token of the user) and on an account edit that changes the email or password; a used token is dead |

Indexes: unique `token_hash`, `user_id`.

## Pending decisions

None.
