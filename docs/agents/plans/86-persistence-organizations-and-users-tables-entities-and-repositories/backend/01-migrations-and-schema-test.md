# Migrations and schema test

Write the two migration files exactly as specified in [organizations-and-users.md](../../../specs/organizations/data-model/organizations-and-users.md), following the rules in [schema-migrations.md](../../../specs/persistence/schema-migrations.md):

- one `CREATE TABLE` per file, no `IF NOT EXISTS`;
- `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci`;
- `id BIGINT NOT NULL AUTO_INCREMENT`, `PRIMARY KEY (id)`;
- `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`.

`organizations`: `id`, `name VARCHAR(255) NOT NULL`, `created_at`, `updated_at`.

`users`: `id`, `organization_id BIGINT NOT NULL`, `username VARCHAR(255) NOT NULL`, `email VARCHAR(255) NOT NULL`, `password_digest VARCHAR(255) NOT NULL`, `is_admin BOOLEAN NOT NULL DEFAULT FALSE`, `status ENUM('pending','approved','denied') NOT NULL` (**no default**), `created_at`, `updated_at`. Also:

- `UNIQUE KEY uq_users_username (username)`;
- `UNIQUE KEY uq_users_email (email)`;
- `KEY ix_users_organization_id (organization_id)`;
- `CONSTRAINT fk_users_organization_id FOREIGN KEY (organization_id) REFERENCES organizations (id) ON DELETE RESTRICT`.

Extend `SchemaTest`:

- add `organizations` and `users` to `TABLES`;
- add test methods asserting, through `information_schema`, the columns (name, `COLUMN_TYPE`, `IS_NULLABLE`, `COLUMN_DEFAULT`, `EXTRA` for auto-increment and on-update), the unique keys (`uq_users_username`, `uq_users_email`), the foreign key `fk_users_organization_id` (column, referenced table/column, `DELETE_RULE = RESTRICT`) and the table collation `utf8mb4_0900_ai_ci` for both tables;
- assert that `users.status` has no default.

Add small private helpers to `SchemaTest` (e.g. `columns(string $table)`, `uniqueKeys(string $table)`, `foreignKeys(string $table)`) so #87–#89 can reuse them.

Check that `composer db:status` shows both files applied and none pending.

## Files to Change
- `source/migrations/0001_create_organizations.sql` — new.
- `source/migrations/0002_create_users.sql` — new.
- `source/tests/integration/Schema/SchemaTest.php` — table set, per-table expectations, reusable helpers.
