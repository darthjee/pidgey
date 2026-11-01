# Database

Purpose: define how Pidgey stores data and evolves its schema.

## Access

- MySQL 9.
- Accessed with **raw PDO only** (`pdo_mysql`). **No ORM**, no query builder library.
- Always use prepared statements; `PDO::ERRMODE_EXCEPTION`; `utf8mb4`.
- Every connection (application and migrations alike) disables multi-statements
  (`PDO::MYSQL_ATTR_MULTI_STATEMENTS => false`), so no query can carry stacked statements.
- Connection settings come from environment variables only (`.env` in compose): `DB_HOST`,
  `DB_PORT` (default `3306`), `DB_NAME`, `DB_USER`, `DB_PASSWORD` (default empty), plus the
  optional `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` (see [Database users](#database-users)
  and [configuration.md](configuration.md#runtime-and-database)). There is no
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

## Database users

Two database users can share the same `DB_HOST`, `DB_PORT` and `DB_NAME`:

| Entry point | User |
| --- | --- |
| `bin/migrate` (including `--status`), `bin/create-db`, `bin/drop-db` | Migration user: `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD`, falling back to `DB_USER` / `DB_PASSWORD` when `DB_MIGRATE_USER` is unset. Its settings are loaded by `Pidgey\Infra\DatabaseConfig::forMigrations()`. |
| `bin/wait-for-db`, the web app (`public/index.php`) | App user: always `DB_USER` / `DB_PASSWORD`, loaded by `DatabaseConfig::fromEnv()`. `bin/wait-for-db` only checks that the server accepts connections, so it needs no extra rights. |

- Why: the web app then connects without DDL rights, so SQL issued by the app (e.g. through
  SQL injection) cannot alter or drop the schema. This does not protect against every flaw:
  `DB_MIGRATE_PASSWORD` lives in the same shared `.env` that the web app loads on every
  request, so a file-read or code-execution bug in the web app could still obtain the
  migration credentials (see
  [deployment/known-gaps.md](../../deployment/known-gaps.md#migration-credentials-in-the-shared-env)).
  The grants of each user in production are in
  [deployment/security.md](../deployment/security.md#database-users).
- Docker, CI and tests set no `DB_MIGRATE_*` and use one user through the fallback.

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
  database) and at deploy time. It reads its settings only from the environment (filled from
  `config/.env` when that file exists, see
  [configuration.md](configuration.md#env-file)).
- In production the deploy runs `$PHP_BIN bin/migrate` in the staging folder, after
  `bin/check-config` and **before** the swap, so new code never serves an old schema and a
  failed migration aborts the deploy with the live release untouched (see
  [deployment/release.md](../deployment/release.md#release-release)).
- `wait-for-db` runs before migrations wherever the database may still be starting
  (compose, CI).
- Before integration tests, the test database is created (if missing) and migrated
  (`composer db:prepare`).

### Compatibility with the previous release

Because migrations run before the swap, the previous release keeps serving requests on the new
schema for a while, and keeps doing so if the deploy fails after migrating. Rule: **every
migration must keep working with the previous release's code.**

- **Expand first:** add tables, nullable columns or columns with defaults, and indexes in one
  release.
- **Contract later:** drop or rename columns and tables only in a later release, once no
  released code uses them.

See [deployment/edge-cases.md](../deployment/edge-cases.md#migrations).

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
- The app user (the only user in development and tests) is granted ``ALL ON `pidgey\_%`.*`` by a mysql init script, so it can create
  and drop `pidgey_*` databases without root credentials in application containers.

The tables are specified in [organizations/data-model.md](../organizations/data-model.md). How
application code uses the connection (transactions, entities, repositories) and the initial
migration files are specified in [persistence.md](../persistence.md).
