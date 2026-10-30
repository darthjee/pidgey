# 01 — Migration and schema test

Create `source/migrations/0007_create_api_tokens.sql` with a single `CREATE TABLE api_tokens`
(no `IF NOT EXISTS`):

```sql
CREATE TABLE api_tokens (
    id BIGINT NOT NULL AUTO_INCREMENT,
    organization_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    token_prefix VARCHAR(16) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    scopes JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_tokens_token_hash (token_hash),
    KEY ix_api_tokens_organization_id (organization_id),
    CONSTRAINT fk_api_tokens_organization_id FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

Extend `SchemaTest`:

- add `api_tokens` to the table set;
- `testApiTokensColumns`: every column's type, nullability and default. `scopes` is `json`
  with no default; `last_used_at` and `revoked_at` use the nullable-datetime shape;
- `testApiTokensUniqueKeys`: `uq_api_tokens_token_hash` → `token_hash`;
- `testApiTokensIndexes`: `ix_api_tokens_organization_id` → `organization_id`;
- `testApiTokensForeignKeys`: `organization_id` → `organizations.id`, `RESTRICT`. Add an
  `ORGANIZATION_FK` constant alongside `USER_FK`, and reuse it in the users test if that
  stays clean;
- `testApiTokensCollation`: `utf8mb4_0900_ai_ci`.

## Files to Change

- `source/migrations/0007_create_api_tokens.sql`: new.
- `source/tests/integration/Schema/SchemaTest.php`: `api_tokens` expectations.
