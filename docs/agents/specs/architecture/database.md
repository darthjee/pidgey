# Database

Purpose: define how Pidgey stores data and evolves its schema.

## Access

- MySQL 9.
- Accessed with **raw PDO only** (`pdo_mysql`). **No ORM**, no query builder library.
- Always use prepared statements; `PDO::ERRMODE_EXCEPTION`; `utf8mb4`.
- Every connection (application and migrations alike) disables multi-statements
  (`PDO::MYSQL_ATTR_MULTI_STATEMENTS => false`), so no query can carry stacked statements.
- Connection settings come from environment variables only (`.env` in compose): `DB_HOST`,
  `DB_PORT` (default `3306`), `DB_NAME`, `DB_USER`, `DB_PASSWORD` (default empty). There is no
  `PHP_ENV`-based switching.
- All SQL lives in repository classes (`Pidgey\Repository\`).
- Every query on organization-scoped tables filters by `organization_id`.

## Database scripts

Thin PHP CLI entry points in `source/bin/` (argument parsing and exit codes only; the logic
lives in `Pidgey\Infra`), all PDO-based (no shell, no `mysql` client). They live under
`source/` because only `source/` is mounted in the containers and shipped in the image. Each
exits `0` on success and non-zero on any failure, with the error on stderr. They are exposed
as composer scripts (see [testing/backend.md](../testing/backend.md)):

| Script | Composer | Action |
| --- | --- | --- |
| `bin/wait-for-db` | `db:wait` | Connects to the server (no database selected) in a retry loop until MySQL accepts connections; times out after `DB_WAIT_TIMEOUT` seconds (default 60) with a clear message. |
| `bin/create-db` | `db:create` | `CREATE DATABASE IF NOT EXISTS` for `DB_NAME`. Not part of the deploy flow: production databases are provisioned outside the app. |
| `bin/drop-db` | `db:drop` | `DROP DATABASE IF EXISTS` for `DB_NAME` (used by `make db-reset`). |
| `bin/migrate` | `db:migrate` | Applies pending migrations to `DB_NAME`. |
| `bin/migrate --status` | `db:status` | Read-only: lists applied and pending migrations and flags missing or edited files. |

`composer db:prepare` runs wait, create (if missing) and migrate in sequence.

## Migrations

- Plain SQL files in `source/migrations/`, named `NNNN_description.sql`
  (zero-padded, monotonically increasing). Gaps in version numbers are allowed; only ordering
  matters.
- The migrations runner (`Pidgey\Infra`, hand-rolled; libraries such as Phinx or Doctrine
  Migrations were rejected because their conventions do not fit the rules below) applies
  pending files in version order and records each applied version, with a SHA-256 checksum of
  the file, in a `schema_migrations` table (created if missing).
- Migrations are forward-only; no down migrations.
- The same `migrate` script runs in development, in integration tests (against the test
  database) and, later, at deploy time. It reads its settings only from the environment.
- `wait-for-db` runs before migrations wherever the database may still be starting
  (compose, CI).
- Before integration tests, the test database is created (if missing) and migrated
  (`composer db:prepare`).

### One statement per file

Each migration file contains **exactly one SQL statement**, enforced by the runner. MySQL 8+
DDL is atomic per statement, so a failed migration leaves nothing behind: fix the file and
re-run.

- The runner counts statements with a quote- and comment-aware splitter. More than one
  statement is a validation error; zero statements is the "empty file" error. Comments and a
  trailing `;` are allowed.
- Files are never handed to PDO as multi-statement SQL.
- Indexes and constraints can live inside `CREATE TABLE`; a multi-step data backfill becomes
  several consecutive files.
- Not supported: `DELIMITER`, stored procedures and triggers.

### Idempotency rules

- Re-running the migration script when nothing is pending is a no-op.
- An applied version (recorded in `schema_migrations`) is never reapplied.
- A failing migration stops the run, reports the file and the error, is not recorded in
  `schema_migrations`, and later files are not attempted.
- CI recreates the test database on every run.

### Validation

All of the following fail **before anything is applied**, exit non-zero and name the
offending file(s):

- **Duplicate versions** (e.g. `0003_a.sql` and `0003_b.sql`).
- **Out-of-order pending file**: a pending version lower than the highest applied one.
- **Applied version whose file is missing.**
- **Applied file edited afterwards**: its checksum no longer matches `schema_migrations`.
- **Badly named file**: any `.sql` file not matching `NNNN_description.sql` (non-`.sql`
  files, e.g. a README, are ignored).
- **Empty or whitespace-only file.**
- **Multi-statement file** (see [One statement per file](#one-statement-per-file)).
- **Missing migrations directory** (an empty directory is a no-op).

`migrate --status` flags missing and edited files instead of failing on them.

### Concurrent runs

Runs against the same database are serialized with a MySQL named lock:

- Before doing anything, the runner acquires `GET_LOCK('pidgey_migrations:<DB_NAME>', <timeout>)`.
  The lock is per database (development and test runs never block each other) and
  session-scoped, so MySQL releases it if the process crashes or disconnects.
- Validation and the read of `schema_migrations` happen after the lock is acquired, so a run
  that waited sees what the other one applied.
- Timeout: `MIGRATE_LOCK_TIMEOUT` seconds (default 60). On timeout the run exits non-zero with
  a clear message (another migration run holds the lock on `<db>`) and applies nothing.
- The lock is released explicitly at the end of the run, on success and failure alike.
- `migrate --status` is read-only and does not take the lock.

## Test database

- Integration tests use `pidgey_test` on the same `mysql` service, selected by
  `DB_NAME=pidgey_test` in the `tests` compose service's environment (overriding `.env`'s
  `pidgey_development`). See [docker/compose.md](../docker/compose.md).
- Integration tests refuse to run unless `DB_NAME` ends in `_test`, so the suite can never
  touch the development database.
- The app user is granted ``ALL ON `pidgey\_%`.*`` by a mysql init script, so it can create
  and drop `pidgey_*` databases without root credentials in application containers.

The tables are specified in [organizations/data-model.md](../organizations/data-model.md).
