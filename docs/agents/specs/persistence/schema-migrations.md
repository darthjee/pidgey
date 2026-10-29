# Schema Migrations

Purpose: define the initial migration files that create Pidgey's tables.

File format, runner rules and validation are in
[architecture/database.md](../architecture/database.md#migrations); column definitions in
[organizations/data-model.md](../organizations/data-model.md).

## Rules

- **One table per file**: each file is a single `CREATE TABLE` statement, with its primary
  key, foreign keys, indexes and unique constraints declared inside it
  ([data-model/conventions.md](../organizations/data-model/conventions.md#keys)).
- File name: `NNNN_create_<table>.sql`.
- A table is created after every table it references, so foreign keys resolve.
- Every `CREATE TABLE` states `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci`
  explicitly, so the schema does not depend on server defaults.
- Columns, types, nullability, defaults and indexes match the table spec exactly. When they
  must differ, the table spec is updated first, in the same change.
- Index and constraint names are explicit, so later migrations can refer to them:
  - unique: `uq_<table>_<column>[_<column>]`;
  - plain index: `ix_<table>_<column>[_<column>]`;
  - foreign key: `fk_<table>_<column>`.
- No `IF NOT EXISTS`: the runner's version tracking makes the file run once, and a
  pre-existing table should fail loudly.
- Once merged, a migration file is never edited (the runner's checksum check rejects it); a
  change is a new file.

## Initial files

| File | Table | References |
| --- | --- | --- |
| `0001_create_organizations.sql` | `organizations` | — |
| `0002_create_users.sql` | `users` | `organizations` |
| `0003_create_refresh_tokens.sql` | `refresh_tokens` | `users` |
| `0004_create_password_reset_tokens.sql` | `password_reset_tokens` | `users` |
| `0005_create_authorization_requests.sql` | `authorization_requests` | `users` (twice) |
| `0006_create_account_edit_lockouts.sql` | `account_edit_lockouts` | `users` |
| `0007_create_api_tokens.sql` | `api_tokens` | `organizations` |
| `0008_create_smtp_connections.sql` | `smtp_connections` | `organizations` |

The numbers are assigned in this order. If the tables are implemented in a different order,
the numbers follow the merge order instead (only ordering matters to the runner), as long as
every referenced table comes first.

## Example

Illustrative only.

```sql
-- 0002_create_users.sql
CREATE TABLE users (
    id BIGINT NOT NULL AUTO_INCREMENT,
    organization_id BIGINT NOT NULL,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_digest VARCHAR(255) NOT NULL,
    is_admin BOOLEAN NOT NULL DEFAULT FALSE,
    status ENUM('pending', 'approved', 'denied') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY ix_users_organization_id (organization_id),
    CONSTRAINT fk_users_organization_id FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

## Pending decisions

None. The database-level "at most one active SMTP connection" constraint stays a pending
decision of [email/smtp-connections.md](../email/smtp-connections.md); if adopted, it is a
later migration, not a change to `0008`.
