# Migration and schema test

Create the `smtp_connections` table exactly as the table spec defines it, and add its
expectations to the schema test.

`source/migrations/0008_create_smtp_connections.sql`:

```sql
CREATE TABLE smtp_connections (
    id BIGINT NOT NULL AUTO_INCREMENT,
    organization_id BIGINT NOT NULL,
    name VARCHAR(255) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT NOT NULL,
    encryption ENUM('none', 'tls', 'ssl') NOT NULL,
    username VARCHAR(255) NULL,
    password VARBINARY(1024) NULL,
    from_address VARCHAR(255) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_smtp_connections_organization_id (organization_id),
    CONSTRAINT fk_smtp_connections_organization_id FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

`SchemaTest` additions:
- Add `'smtp_connections'` to `TABLES`, keeping alphabetical order (between `schema_migrations` and
  `users`).
- `testSmtpConnectionsColumns()`:

  | Column | Expected |
  | --- | --- |
  | `id` | `ID` |
  | `organization_id` | `BIGINT` |
  | `name`, `host` | `VARCHAR_255` |
  | `port` | `['type' => 'int', 'nullable' => false, 'default' => null, 'extra' => '']` |
  | `encryption` | `"enum('none','tls','ssl')"`, not nullable, no default |
  | `username` | `varchar(255)`, nullable, default `null` |
  | `password` | `varbinary(1024)`, nullable, default `null` |
  | `from_address` | `VARCHAR_255` |
  | `active` | `tinyint(1)`, not nullable, default `'0'` (same shape as `users.is_admin`) |
  | `created_at` | `CREATED_AT` |
  | `updated_at` | `UPDATED_AT` |

  Add a constant (e.g. `NULLABLE_VARCHAR_255`) only if it reads better; otherwise inline the
  arrays, as `api_tokens.token_prefix` does.
- `testSmtpConnectionsUniqueKeys()`: expects `[]`, so the table has no unique keys.
- `testSmtpConnectionsIndexes()`: `['ix_smtp_connections_organization_id' => ['organization_id']]`.
- `testSmtpConnectionsForeignKeys()`: `['fk_smtp_connections_organization_id' => self::ORGANIZATION_FK]`.
- `testSmtpConnectionsCollation()`.

## Files to Change
- `source/migrations/0008_create_smtp_connections.sql`: new migration.
- `source/tests/integration/Schema/SchemaTest.php`: table set plus the `smtp_connections`
  expectations.
