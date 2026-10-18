# Backend Plan: Add DB infrastructure and migrations runner

Main plan: [plan.md](plan.md)

## Shared contracts

Backend **produces**: the env variables it reads (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
`DB_PASSWORD`, `DB_WAIT_TIMEOUT` default 60, `MIGRATE_LOCK_TIMEOUT` default 60), the CLI entry
points `source/bin/{wait-for-db,create-db,drop-db,migrate}` (`migrate --status` read-only; exit 0
on success, non-zero with a stderr message otherwise), and the composer scripts `db:wait`,
`db:create`, `db:drop`, `db:migrate`, `db:status`, `db:prepare` (wait → create → migrate), with
`tests` and `tests:integration` running `@db:prepare` first and `tests:unit` unchanged.

Backend **relies on** infra for: `DB_NAME=pidgey_test` on the `tests` service, and the
``pidgey\_%`` grant to `DB_USER` (needed to create `pidgey_test` and throwaway
`pidgey_runner_<random>_test` databases in integration tests).

## Steps

- [01 — DB configuration and PDO factory](backend/01-db-config-and-pdo-factory.md)
- [02 — Migration file discovery and validation](backend/02-migration-discovery-and-validation.md)
- [03 — Migrations runner, lock and status](backend/03-migrations-runner.md)
- [04 — Database admin helpers (wait, create, drop)](backend/04-database-admin.md)
- [05 — CLI entry points and composer scripts](backend/05-cli-and-composer-scripts.md)
- [06 — Integration test support and runner integration tests](backend/06-integration-tests.md)

## CI Checks

No CI config exists yet (`.circleci/` is planned). Run locally:

- `source/`: `docker compose run --rm tests sh -c "composer lint && composer tests"` (`make tests`)

## Notes

- Follow the existing style of `Pidgey\Infra\Env`: `declare(strict_types=1);`, `final` classes,
  full PHPDoc on non-test code (phpcs `Squiz.Commenting.FunctionComment`), PSR-12.
- Keep classes small and injectable (PDO, clock/sleeper, directory path passed in) so the logic
  is unit-testable without MySQL; only SQL-touching behavior goes in integration tests.
- `bin/` must be added to the lint paths (`phpcs.xml` `<file>bin</file>` and the phpmd path list
  in `composer.json`'s `lint`/`lint:fix`) so the entry points are linted like the rest.
- `source/migrations/` is created with a `.gitkeep` only — no real application tables in this
  issue.
- `PDO::MYSQL_ATTR_MULTI_STATEMENTS` only takes effect as a constructor option; set it there.
