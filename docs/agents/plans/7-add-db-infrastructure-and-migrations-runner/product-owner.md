# Product Owner Plan: Add DB infrastructure and migrations runner

Main plan: [plan.md](plan.md)

## Shared contracts

Product owner **relies on** the contracts in [plan.md](plan.md#shared-contracts) (CLI entry
points in `source/bin/`, composer `db:*` scripts, `DB_NAME=pidgey_test` on `tests`, the
`pidgey\_%` grant init script, `make migrate` / `make db-reset`) and documents them as the
intended design. Specs are updated in the same change set as the code (AGENTS.md rule 2).

## Implementation Steps

### Step 1 — Database and layout specs

- `docs/agents/specs/architecture/database.md`:
  - Migrations: the migration and wait-for-db scripts are PHP CLIs in `source/bin/` (backed by
    `Pidgey\Infra`), exposed as `composer db:*` scripts; replace "in `scripts/`".
  - Replace the known-limitation paragraph ("keep one DDL concern per migration file") with:
    exactly one statement per migration file, enforced by the runner (MySQL 8+ DDL is atomic per
    statement, so a failed migration leaves nothing behind).
  - Add the decided rules: checksums of applied files are verified; duplicate / out-of-order /
    missing / badly named / empty / multi-statement files fail before anything runs; runs are
    serialized per database with `GET_LOCK` (`MIGRATE_LOCK_TIMEOUT`, default 60 s);
    `migrate --status` is read-only; `PDO` connections disable multi-statements.
  - Test database: `pidgey_test`, selected by `DB_NAME` on the `tests` service; integration tests
    refuse to run unless `DB_NAME` ends in `_test`.
- `docs/agents/specs/architecture/repository-layout.md`: `scripts/` row → "Host-side / CI-only
  operational scripts (DB scripts live in `source/bin/`, since only `source/` ships in the
  image)"; add `source/bin/` to the "Inside `source/`" table.
- `docs/agents/folder-structure.md`: same `scripts/` wording; add `source/bin/`; drop
  *(planned)* from `source/migrations/`.

### Step 2 — Docker and testing specs

- `docs/agents/specs/docker/compose.md`: `mysql` mounts `dockerfiles/mysql/initdb.d` (grant on
  `pidgey\_%` to the app user); `tests` sets `DB_NAME=pidgey_test`.
- `docs/agents/specs/docker/makefile.md`: add `migrate` and `db-reset` rows; mention the
  one-time volume reset for the new grant next to the existing "Changing `MYSQL_*`" note.
- `docs/agents/specs/testing/backend.md`: composer scripts table gains `db:wait`, `db:create`,
  `db:drop`, `db:migrate`, `db:status`, `db:prepare`; `tests` / `tests:integration` run
  `db:prepare` first, `tests:unit` does not; runner integration tests use throwaway
  `pidgey_runner_<random>_test` databases and fixture sets in
  `source/tests/fixtures/migrations/`.

## Files to Change
- `docs/agents/specs/architecture/database.md` — script location, one-statement rule, runner rules, test DB.
- `docs/agents/specs/architecture/repository-layout.md` — `scripts/` vs `source/bin/`.
- `docs/agents/folder-structure.md` — same, plus `source/migrations/` no longer planned.
- `docs/agents/specs/docker/compose.md` — mysql init mount, tests `DB_NAME`.
- `docs/agents/specs/docker/makefile.md` — new targets, volume-reset note.
- `docs/agents/specs/testing/backend.md` — db composer scripts, test DB isolation.

## Notes
- Pure documentation; no code. Keep wording as "intended design" consistent with the rest of
  the specs.
