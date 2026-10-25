# API Tokens Table

Purpose: define the `api_tokens` table holding organization API tokens.

Token format, scopes and revocation rules are defined in
[api/authentication.md](../../api/authentication.md). Conventions are in
[conventions.md](conventions.md).

## `api_tokens`

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | BIGINT | no | auto | PK |
| `organization_id` | BIGINT | no | | FK → `organizations.id`; indexed |
| `name` | VARCHAR(255) | no | | Label shown in the UI |
| `token_prefix` | VARCHAR(16) | no | | Non-secret display prefix only (never part of the secret), for identification |
| `token_hash` | CHAR(64) | no | | SHA-256 hex of the full token; **unique** |
| `scopes` | JSON | no | | Array of scope names, e.g. `["send"]` |
| `created_at` | DATETIME | no | now | |
| `last_used_at` | DATETIME | yes | null | Updated on successful use |
| `revoked_at` | DATETIME | yes | null | Set on revocation; a revoked token is rejected |

Indexes: unique `token_hash`, `organization_id`.

Scopes are stored as a `JSON` column, not a join table.

## Pending decisions

None.
