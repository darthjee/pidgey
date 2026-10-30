# Backend Plan: Persistence foundation: connection, transactions, helpers and test support

Main plan: [plan.md](plan.md)

## Overview
Implement the foundation specified in `docs/agents/specs/persistence/` (`connection.md`, `entities.md`, `repositories.md`, `testing.md`) and refined in issue #85. Every class gets PHPDoc (required outside tests by phpcs) and `declare(strict_types=1);`.

## Context
- `PdoFactory` / `ConnectionFactory` / `DatabaseConfig` already exist in `source/lib/Infra/`; the migrations runner and `bin/` scripts use them.
- `Pidgey\Container` does not exist; `source/public/index.php` calls `HealthHandler` directly and must stay that way.
- `source/tests/support/IntegrationTestCase.php` already guards `DB_NAME` ending in `_test` and exposes `config()` / `pdo()`.
- `source/migrations/` is empty, so `pidgey_test` only holds `schema_migrations`.

## Steps

- [01 — UTC session time zone in PdoFactory](backend/01-pdo-factory-time-zone.md)
- [02 — Row and Sql conversion helpers](backend/02-row-and-sql-helpers.md)
- [03 — Transaction helper](backend/03-transaction-helper.md)
- [04 — Container with pdo() and transaction()](backend/04-container.md)
- [05 — Integration test support](backend/05-test-support.md)

## CI Checks
- `source`: `composer lint` (CI job: lint)
- `source`: `composer tests` — unit and integration suites against MySQL (CI job: tests)

## Notes
- Use the numeric offset `'+00:00'`, never `'UTC'` (named zones need MySQL time zone tables).
- Exception messages from `Row` must name the column and entity/context only, never the value.
- `Transaction::run()` rethrows the original exception even when `rollBack()` itself throws.
- `Fixtures` starts with no table methods; #86–#89 add them. Keep the phpmd unused-code gate green (no unused private members).
- If any detail must differ from `docs/agents/specs/persistence/`, update the spec in the same change.
