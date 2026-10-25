# Refresh Tokens Table

Purpose: define the `refresh_tokens` table backing admin UI sessions.

Each row is one refresh token of a session; rotation creates a new row in the same session.
Rotation, replay detection and revocation rules are defined in
[api/authentication/sessions.md](../../api/authentication/sessions.md) and the session
endpoints in [api/endpoints/auth/session.md](../../api/endpoints/auth/session.md); the
token lifetime (`REFRESH_TOKEN_TTL`) in
[architecture/configuration.md](../../architecture/configuration.md). Conventions are in
[conventions.md](conventions.md).

## `refresh_tokens`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | BIGINT | no | auto | PK |
| `token_hash` | CHAR(64) | no | | SHA-256 hex of the refresh token; **unique** |
| `user_id` | BIGINT | no | | FK → `users.id`; indexed. Owner of the session |
| `issued_at` | DATETIME | no | now | When this token was issued |
| `expires_at` | DATETIME | no | | When this token stops being accepted |
| `revoked_at` | DATETIME | yes | null | When this token was revoked |
| `revoked_reason` | VARCHAR(32) | yes | null | `rotated` / `logout` / `password_reset` / `replay_detected` / `admin_denied`; set together with `revoked_at` |
| `session_uuid` | VARCHAR(36) | no | | Session id, shared by every token of the same session; indexed |
| `started_at` | DATETIME | no | | When the session's login happened; copied on rotation |

Indexes: unique `token_hash`, `user_id`, `session_uuid`.

## Pending decisions

None.
