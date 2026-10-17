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

The data model is sketched in [organizations/data-model.md](../organizations/data-model.md).
