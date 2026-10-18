# Infra Plan: Add DB infrastructure and migrations runner

Main plan: [plan.md](plan.md)

## Shared contracts

Infra **produces**:

- `tests` service: `environment.DB_NAME: pidgey_test` (next to `PHP_ENV: test`).
- `mysql` service: `./dockerfiles/mysql/initdb.d:/docker-entrypoint-initdb.d:ro` mount, with
  `dockerfiles/mysql/initdb.d/01-grant-pidgey-dbs.sh` granting ``ALL ON `pidgey\_%`.*`` to
  `$MYSQL_USER`@`%`.
- `make migrate` → `docker compose run --rm app composer db:prepare`.
- `make db-reset` → `docker compose run --rm app sh -c "composer db:drop && composer db:prepare"`.

Infra **relies on** backend for the composer scripts `db:prepare` (wait → create → migrate),
`db:drop`, `db:migrate`, `db:status`, and on `composer tests` / `tests:integration` running
`@db:prepare` themselves (no compose `command:` wiring needed).

## Steps

- [01 — MySQL grant init script and compose wiring](infra/01-mysql-init-and-compose.md)
- [02 — Makefile targets](infra/02-makefile-targets.md)
- [03 — README DB commands](infra/03-readme-db-commands.md)

## CI Checks

No CI config exists yet. Verify locally on a **fresh** `docker_volumes/mysql_data`:

- `make tests` (lint + all suites, which runs `db:prepare` against `pidgey_test`).
- `make migrate` twice (second run: "Nothing to migrate."), then `make db-reset`.

## Notes

- The init script only runs on an empty `docker_volumes/mysql_data`; existing developers must
  reset the volume once (documented in step 03).
- Use a `.sh` init script (not `.sql`) so it can read `$MYSQL_USER` instead of hard-coding
  `pidgey`; inside the mysql entrypoint, sourced `.sh` files have `docker_process_sql`
  available, and `MYSQL_ROOT_PASSWORD` is already applied. Mind the shell quoting so the
  backticks and the `\_` escape reach MySQL intact. The file must be executable or is sourced —
  either works with the official image; keep it `chmod 0755` anyway.
- `app` already `depends_on: mysql`, so `docker compose run --rm app …` starts MySQL; `db:wait`
  covers the start-up race.
