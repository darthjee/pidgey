# Database

Purpose: define how Pidgey stores data and evolves its schema.

## Access

- MySQL 9.
- Accessed with **raw PDO only** (`pdo_mysql`). **No ORM**, no query builder library.
- Always use prepared statements; `PDO::ERRMODE_EXCEPTION`; `utf8mb4`.
- All SQL lives in repository classes (`Pidgey\Repository\`).
- Every query on organization-scoped tables filters by `organization_id`.

## Migrations

- Plain SQL files in `source/migrations/`, named `NNNN_description.sql`
  (zero-padded, monotonically increasing).
- A migration script (in `scripts/`, backed by `Pidgey\Infra` code) applies pending
  files in order and records applied versions in a `schema_migrations` table.
- Migrations are forward-only; no down migrations.
- The same script runs in development, in integration tests (against a test database)
  and at deploy time.
- A wait-for-db script (in `scripts/`) blocks until MySQL accepts connections; it runs
  before migrations wherever the database may still be starting (compose, CI).
- Before integration tests, the test database is created (if missing) and migrated.

### Idempotency rules

- Re-running the migration script when nothing is pending is a no-op.
- An applied version (recorded in `schema_migrations`) is never reapplied.
- A failing migration stops the run; it is not recorded in `schema_migrations`, and later
  files are not applied.
- CI recreates the test database on every run.

Known limitation: MySQL DDL statements auto-commit, so a failed migration may leave partial
changes behind. Keep one DDL concern per migration file to limit the impact.

The data model is sketched in [organizations/data-model.md](../organizations/data-model.md).
