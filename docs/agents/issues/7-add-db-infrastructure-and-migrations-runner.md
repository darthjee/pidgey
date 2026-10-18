# Issue: Add DB infrastructure and migrations runner

## Description

Part of the backend bootstrap Epic (#3). Add the database infrastructure the backend needs
before any feature: environment-based configuration, a PDO factory, and a forward-only SQL
migrations runner, wired into Docker Compose. Follows
`docs/agents/specs/architecture/database.md` and `docs/agents/specs/architecture/backend.md`.

Depends on: the lint and tests sub-issue (and, through it, the Docker dev environment with the
`mysql` service).

## Problem

The backend has no way to reach or evolve its database yet: no DB configuration or PDO
factory, no migrations runner, and no wiring that prepares a test database before
integration tests. The dockerized `mysql` user also lacks the privileges to create a separate
test database, and the repo-root `scripts/` folder named by the specs is not visible inside
the containers.

## Expected Behavior

- `docker compose run --rm tests composer tests` runs the integration suite against a freshly
  created and migrated test database.
- Running migrate twice in a row leaves the schema and `schema_migrations` unchanged.
- A deliberately failing migration in a test fixture stops the run without being recorded.
- `composer tests:unit` runs without MySQL (no `db:prepare`).
- On a fresh `mysql` volume, `composer db:prepare` creates `pidgey_test` as the normal
  `DB_USER` (no root credentials in application containers).
- `make migrate` and `make db-reset` act on `pidgey_development`, even though `make dev`'s
  shell points at `pidgey_test`.
- Integration tests refuse to run when `DB_NAME` does not end in `_test`.
- Each edge case listed above (duplicate/out-of-order/missing/edited/badly named/empty files,
  multi-statement files, missing directory) fails before anything is applied, exits non-zero
  and names the offending file.
- With the migrations lock held by another connection, `migrate` times out without applying
  anything.
- `migrate --status` lists applied and pending migrations and flags missing/edited files.

## Solution

### Scope

- **`Pidgey\Infra` configuration**: loads DB settings (host, port, name, user, password) from
  environment variables (`.env` in compose).
- **PDO factory**: `pdo_mysql`, `PDO::ERRMODE_EXCEPTION`, `utf8mb4`, prepared statements only,
  and `PDO::MYSQL_ATTR_MULTI_STATEMENTS => false` on every connection (application and
  migrations alike), so no query can ever carry stacked statements.
- **Migrations runner** (`Pidgey\Infra`): applies plain SQL files from `source/migrations/`
  named `NNNN_description.sql` in version order, recording applied versions in a
  `schema_migrations` table (created if missing). Forward-only, no down migrations.
  Hand-rolled on purpose: libraries such as Phinx or Doctrine Migrations were rejected — they
  bring their own config and conventions, which don't fit the one-statement, checksum and
  `GET_LOCK` rules below. The migrations directory is a constructor argument; the CLI
  defaults it to `source/migrations/`.
- **DB scripts** — thin PHP CLI entry points in **`source/bin/`** (argument parsing and exit
  codes only; logic lives in `Pidgey\Infra`), all PDO-based (no shell, no `mysql` client):
  - `wait-for-db`: connects to the server (no database) in a retry loop until MySQL accepts
    connections; timeout `DB_WAIT_TIMEOUT` (default 60 s), clear failure message.
  - `create-db`: `CREATE DATABASE IF NOT EXISTS` for `DB_NAME`.
  - `migrate` (with `--status`): runs the migrations runner against the configured database.

  They live under `source/` rather than the repo-root `scripts/` because only `./source` is
  mounted in the containers and it is the image build context (`scripts/` could not be
  copied into CI/prod images). The repo-root `scripts/` folder is not created by this issue.
- **Composer scripts** as the front door: `composer db:wait`, `db:create`, `db:migrate`,
  `db:status`, and `db:prepare` (wait → create → migrate).
- **Test wiring in composer, not compose**: `composer tests` and `composer tests:integration`
  run `@db:prepare` first; `composer tests:unit` does not (unit tests stay runnable without
  MySQL). Compose `command:` is not used for this, because both
  `docker compose run --rm tests composer tests` and `make dev` (`bash` on the `tests`
  service) bypass it; a PHPUnit bootstrap hook is rejected as too implicit.
- **Tests**: unit tests for configuration and runner logic; integration tests for the runner
  against the dockerized MySQL server:
  - Fixture migration sets live in `source/tests/fixtures/migrations/<case>/` (failing file,
    duplicates, multi-statement, badly named, …) and are passed to the runner as its
    migrations directory.
  - Each runner integration test creates a throwaway database (e.g.
    `pidgey_runner_<random>_test` — covered by the `pidgey\_%` grant and satisfying the
    `_test` guard) and drops it in teardown, so `pidgey_test` only ever holds the real
    migrations.
- **Docs**: document the DB commands, and update the specs:
  - `docs/agents/specs/architecture/database.md`: the "keep one DDL concern per migration
    file" known-limitation paragraph becomes "exactly one statement per migration file,
    enforced by the runner"; the migration and wait-for-db scripts live in `source/bin/`.
  - `docs/agents/specs/architecture/repository-layout.md`: DB scripts live in `source/bin/`
    (only `source/` ships in the image); repo-root `scripts/` is kept for future host-side /
    CI-only scripts.
- **Standalone migrate CLI**: the migrate script reads DB settings only from the environment
  and exits non-zero on failure, so a later deploy step can call it unchanged.
- **Read-only status**: `migrate --status` lists applied and pending migrations without
  changing anything.
- **Makefile targets**: `make migrate` runs `docker compose run --rm app composer db:prepare`
  (wait, create if missing, migrate the development database); `make db-reset` drops the
  development database, then runs `db:prepare`.
- **Test database selection**: the `tests` compose service sets `DB_NAME: pidgey_test` in its
  `environment` (next to `PHP_ENV: test`), overriding `.env`'s `pidgey_development`. The
  configuration code stays env-only — no `PHP_ENV`-based switching.
- **Database privileges (mysql init script)**: the `mysql:9` image only grants `MYSQL_USER`
  on `MYSQL_DATABASE`, which is not enough to create `pidgey_test` or to drop/recreate the
  development database. Add `dockerfiles/mysql/initdb.d/01-grant-pidgey-dbs.sh`, mounted
  read-only into the `mysql` service at `/docker-entrypoint-initdb.d`, which runs (via the
  image's `docker_process_sql` helper, reading `$MYSQL_USER` from the env rather than
  hard-coding it) ``GRANT ALL ON `pidgey\_%`.* TO '<MYSQL_USER>'@'%'`` — the `\_` escape
  matters, since `_` is a wildcard. This covers `pidgey_development`, `pidgey_test` and any
  future `pidgey_*` database, so `create-db` and `db-reset` work as the normal `DB_USER`;
  application containers never receive root credentials. Init scripts only run on an empty
  volume (see Backward compatibility). `create-db` is not part of the deploy flow — production
  databases are provisioned outside the app.
- **Test database safety guard**: the integration-test bootstrap refuses to run unless
  `DB_NAME` ends in `_test`, so the suite can never wipe the development database.
- **Dev-DB targeting**: since `make dev` opens a shell on the `tests` service (where
  `DB_NAME=pidgey_test`), `make migrate` / `make db-reset` target the development database
  explicitly (via the `app` service); the docs explain how to migrate the development
  database from inside the dev shell (`DB_NAME=pidgey_development composer db:migrate`).

### Edge cases (migrations idempotency)

- Re-running migrate with nothing pending is a no-op.
- A version already in `schema_migrations` is never reapplied.
- A failing migration stops the run, reports the file and the error, does **not** record its
  version, and later files are not attempted.
- **Duplicate versions** (e.g. `0003_a.sql` and `0003_b.sql`): the run refuses to start,
  lists the conflicting files and applies nothing.
- **Gaps** in version numbers (e.g. `0001`, `0003`) are allowed — only ordering matters.
- **Out-of-order pending file** (a pending version lower than the highest applied one, e.g.
  a merged branch adds `0004` after `0005` was applied): the run refuses to start and names
  the file.
- **Applied version whose file is missing**: `migrate` fails; `migrate --status` flags it.
- **Applied file edited afterwards**: `schema_migrations` stores a SHA-256 checksum of each
  applied file; a mismatch makes `migrate` fail and is flagged by `migrate --status`.
- **Badly named files**: any `.sql` file in `source/migrations/` not matching
  `NNNN_description.sql` is an error; non-`.sql` files (e.g. a README) are ignored.
- **Empty or whitespace-only migration file**: error.
- **Missing `source/migrations/` directory**: error; an empty directory is a no-op.
- Every refusal/error case above applies nothing (validation happens before the first
  migration runs) and exits non-zero.
- MySQL DDL auto-commits, so a multi-statement migration that fails midway would leave earlier
  statements applied — see [One statement per file](#one-statement-per-file).

### One statement per file

Each migration file contains **exactly one SQL statement**, enforced by the runner:

- MySQL 8+ DDL is atomic per statement, so a failing single-statement migration leaves nothing
  behind: fix the file and re-run. Multiple statements would leave partial changes and a
  version that cannot simply be re-run.
- A small quote- and comment-aware splitter counts the statements in each file. More than one
  statement is a validation error (raised before anything runs, naming the file); zero
  statements is the "empty file" error above. Comments and a trailing `;` are allowed.
- Files are never handed to `PDO::exec` as multi-statement SQL — `pdo_mysql` can silently
  swallow errors from later statements.
- Indexes and constraints can live inside `CREATE TABLE`; a multi-step data backfill becomes
  several consecutive files.
- Not supported: `DELIMITER`, stored procedures and triggers.

### Concurrent migrate runs

Two runs can target the same database (e.g. parallel `tests` runs, or — later — several
instances migrating at deploy). The runner serializes them with a MySQL named lock:

- Before doing anything, the runner acquires
  `GET_LOCK('pidgey_migrations:<DB_NAME>', <timeout>)`. The lock is per database (dev and test
  runs never block each other; keep the name ≤ 64 characters) and session-scoped, so MySQL
  releases it automatically if the process crashes or disconnects — no stale locks, no lock
  table. (A lock row with `SELECT … FOR UPDATE` would not work: DDL auto-commits and releases
  it.)
- All validation (the edge cases above) and the read of `schema_migrations` happen **after**
  the lock is acquired, so a runner that waited sees what the other one applied.
- Lock timeout defaults to 60 seconds, overridable with `MIGRATE_LOCK_TIMEOUT`. On timeout the
  run exits non-zero with a clear message (another migration run holds the lock on `<db>`)
  and applies nothing.
- The runner calls `RELEASE_LOCK` explicitly at the end, on success and failure alike.
- `migrate --status` is read-only and does not take the lock.
- Integration test: with the lock held from a second PDO connection, the runner (with a short
  timeout) fails without applying anything.

### Backward compatibility

No application data exists yet, so the only impact is on developers with an existing
`docker_volumes/mysql_data` volume: the new init grant never runs there. The docs give two
one-time fixes — delete `docker_volumes/mysql_data` and restart `mysql` (recommended, nothing
is lost at this stage), or run the same `GRANT` manually as root against the running `mysql`
service.

### Out of scope

- Real application tables (organizations, users, …) — feature issues.
- CircleCI DB steps (CI sub-issue).
- Deploy-time wiring of the migrate script (deployment issue).
- A shared repository base class or DB helper for `Pidgey\Repository\` — comes with the first
  feature that needs it.
- Seed data or fixture loading.
- Rollback / down migrations.

## Benefits

- Feature issues can add tables with a single, predictable, idempotent migration mechanism
  used identically in development, tests and (later) deploy.
- Integration tests always run against a freshly prepared, isolated test database and can
  never touch the development database.
- Failure modes (bad files, edited history, concurrent runs) fail fast and loudly instead of
  leaving a half-migrated schema.
