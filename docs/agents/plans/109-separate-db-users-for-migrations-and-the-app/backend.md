# Backend Plan: Separate DB users for migrations and the app

Main plan: [plan.md](plan.md)

## Shared contracts

- Optional `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD`. When neither is set, fall back to `DB_USER` / `DB_PASSWORD`. `DB_HOST` / `DB_PORT` / `DB_NAME` are shared.
- The migration user (or the fallback) is used by `migrate` (including `--status`), `createDb` and `dropDb`. The app user (`DatabaseConfig::fromEnv()`, unchanged) is used by `waitForDb` and `Container`.
- `bin/check-config` failure lines: `DB_MIGRATE_PASSWORD: missing` (user set, password not) and `DB_MIGRATE_USER: missing` (password set, user not). Values are never printed.

## Steps

- [01 — Migration config loader](backend/01-migration-config-loader.md)
- [02 — Split DatabaseCommand loaders](backend/02-split-database-command-loaders.md)
- [03 — Validate the pairing in check-config](backend/03-validate-pairing.md)

## CI Checks

- `source/`: `make tests` (`composer lint && composer tests` in docker; CI jobs: `backend_checks`, `backend_tests`)

## Notes

- `Container`, `IntegrationTestCase` and every `bin/` script keep calling the same code; only `DatabaseCommand::fromEnvironment()` changes what it wires.
- Do not touch `.env`, docker-compose or CI: they rely on the fallback.
- Ask `security` for a read-only review once the change is done (credentials handling, never echoing values).
