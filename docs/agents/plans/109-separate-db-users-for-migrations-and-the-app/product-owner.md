# Product-owner Plan: Separate DB users for migrations and the app

Main plan: [plan.md](plan.md)

## Shared contracts

- Optional `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD`, with a fallback to `DB_USER` / `DB_PASSWORD`. They're used by `bin/migrate`, `bin/create-db` and `bin/drop-db`. `bin/wait-for-db` and the app always use `DB_USER`.
- `bin/check-config` pairing rule: `DB_MIGRATE_PASSWORD: missing` / `DB_MIGRATE_USER: missing`.
- Grants:
  - Migration user: `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `INDEX`, `ALTER`, `REFERENCES`.
  - App user: `SELECT`, `INSERT`, `UPDATE`, `DELETE`.

## Implementation Steps

### Step 1 — Configuration and database specs

- `specs/architecture/configuration.md`: add `DB_MIGRATE_USER` (default: none, falls back to `DB_USER`) and `DB_MIGRATE_PASSWORD` (default: none, falls back to `DB_PASSWORD`) to the "Runtime and database" table, saying which commands use them. Add the pairing rule to the rules that refuse boot / the `bin/check-config` section.
- `specs/architecture/database.md` and `specs/persistence/connection.md`: where they describe the `DB_*` settings and `DatabaseConfig::fromEnv()`, mention that the schema/admin CLI commands use the migration user, through `DatabaseConfig::forMigrations()`.

### Step 2 — Deployment security and server specs

- `specs/deployment/security.md`:
  - Remove "Database user" from **Known gaps**.
  - Resolve the "Pending decision — migration DB user" entry, and record it as a decision: two users, with each one's grants.
  - Note that the code supports the split from this release on, and that switching production over is a manual step done by the owner, in the rollout order from [plan.md](plan.md#shared-contracts).
- `specs/deployment/server.md`: the MySQL host requirement and the "To confirm" grants checkbox now describe two users (migration user with DDL + `REFERENCES`; app user with data access only).

## Files to Change

- `docs/agents/specs/architecture/configuration.md`: new keys, plus the check-config rule.
- `docs/agents/specs/architecture/database.md`: which user each command uses.
- `docs/agents/specs/persistence/connection.md`: mention `forMigrations()`.
- `docs/agents/specs/deployment/security.md`: gap → decision; pending item resolved.
- `docs/agents/specs/deployment/server.md`: two users and their grants.

## Notes

- Keep values out of every example. Document the key names only.
