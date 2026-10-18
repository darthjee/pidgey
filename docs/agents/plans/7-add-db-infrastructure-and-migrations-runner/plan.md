# Plan: Add DB infrastructure and migrations runner

Issue: [7-add-db-infrastructure-and-migrations-runner.md](../../issues/7-add-db-infrastructure-and-migrations-runner.md)

## Overview

Build the backend's database foundation: env-based DB configuration, a hardened PDO factory and
a hand-rolled, forward-only migrations runner in `Pidgey\Infra`, exposed through thin PHP CLIs in
`source/bin/` and `composer db:*` scripts. Infra wires the test database (compose override, mysql
init grant, Makefile targets, README); the product owner updates the specs that this issue
changes (scripts move from `scripts/` to `source/bin/`, one statement per migration file).

## Agents involved

- [backend](backend.md)
- [infra](infra.md)
- [product-owner](product-owner.md)

## Shared contracts

### Environment variables

| Variable | Default | Read by | Notes |
| --- | --- | --- | --- |
| `DB_HOST` | — (required) | backend | Already in `.env` (`mysql`). |
| `DB_PORT` | `3306` | backend | Already in `.env`. |
| `DB_NAME` | — (required) | backend | `.env`: `pidgey_development`; the `tests` service overrides it to `pidgey_test`. |
| `DB_USER` | — (required) | backend | Already in `.env` (`pidgey`). |
| `DB_PASSWORD` | `''` | backend | Already in `.env`. |
| `DB_WAIT_TIMEOUT` | `60` (seconds) | backend (`wait-for-db`) | Not added to `.env`; default applies. |
| `MIGRATE_LOCK_TIMEOUT` | `60` (seconds) | backend (`migrate`) | Not added to `.env`; default applies. |

### CLI entry points (backend produces, infra/docs consume)

All under `source/bin/`, executable PHP scripts (`#!/usr/bin/env php`), exit `0` on success and
non-zero on any failure, error message on stderr:

- `bin/wait-for-db` — waits until the MySQL server accepts connections (no database selected).
- `bin/create-db` — `CREATE DATABASE IF NOT EXISTS` for `DB_NAME`.
- `bin/drop-db` — `DROP DATABASE IF EXISTS` for `DB_NAME` (used by `make db-reset`).
- `bin/migrate` — applies pending migrations; `bin/migrate --status` is read-only.

### Composer scripts (in `source/composer.json`)

| Script | Runs |
| --- | --- |
| `db:wait` | `bin/wait-for-db` |
| `db:create` | `bin/create-db` |
| `db:drop` | `bin/drop-db` |
| `db:migrate` | `bin/migrate` |
| `db:status` | `bin/migrate --status` |
| `db:prepare` | `@db:wait`, `@db:create`, `@db:migrate` |
| `tests` | `@db:prepare`, then the existing `phpunit --coverage-html coverage` |
| `tests:integration` | `@db:prepare`, then the existing `phpunit --testsuite integration` |
| `tests:unit` | unchanged (no DB) |

### Docker / MySQL

- `tests` service: `environment.DB_NAME: pidgey_test` (next to `PHP_ENV: test`).
- `mysql` service: mounts `./dockerfiles/mysql/initdb.d:/docker-entrypoint-initdb.d:ro`.
- Init script `dockerfiles/mysql/initdb.d/01-grant-pidgey-dbs.sh` grants
  ``ALL ON `pidgey\_%`.*`` to `$MYSQL_USER`@`%`, so `DB_USER` can create/drop `pidgey_test`,
  `pidgey_development` and throwaway `pidgey_runner_<random>_test` databases.
- `make migrate` = `docker compose run --rm app composer db:prepare`;
  `make db-reset` = `docker compose run --rm app sh -c "composer db:drop && composer db:prepare"`.
