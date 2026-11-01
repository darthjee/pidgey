# Migration config loader

Add `DatabaseConfig::forMigrations(): self` (static, next to `fromEnv()`). It reads `DB_HOST`, `DB_PORT` and `DB_NAME` exactly like `fromEnv()`. When `DB_MIGRATE_USER` is set (non-empty, per `Env::get`), it uses `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` (password via `Env::get(..., '') ?? ''`, the same as today's `DB_PASSWORD` handling). Otherwise it returns the same values as `fromEnv()`. Reuse `fromEnv()` internally, for example by building from it and overriding user and password, so the host/port/name parsing isn't duplicated. Document it with the same docblock style.

The pairing rule itself is enforced by `bin/check-config` (step 03). The loader only keys the switch on `DB_MIGRATE_USER`.

Tests in `DatabaseConfigTest`: add `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` to the `VARIABLES` cleanup list, then cover:

- fallback to `DB_USER` / `DB_PASSWORD` when unset;
- the migration credentials are used when set;
- host, port and name are shared;
- `fromEnv()` ignores `DB_MIGRATE_*`.

## Files to Change

- `source/lib/Infra/DatabaseConfig.php`: add `forMigrations()`.
- `source/tests/unit/Infra/DatabaseConfigTest.php`: new cases, plus the cleanup list.
