# Split DatabaseCommand loaders

`DatabaseCommand` has one `$configLoader` closure, which all four commands share. Split it in two:

- `$configLoader`: the app user (`DatabaseConfig::fromEnv()`). Used by `waitForDb()`.
- `$migrationConfigLoader`: the migration user (`DatabaseConfig::forMigrations()`). Used by `createDb()`, `dropDb()` and `migrate()` (both apply and `--status`).

Add the new constructor parameter, documented in the docblock like the others. Wire it in `fromEnvironment()`, and update the `fromEnvironment` docblock (it mentions `DB_*`) to name `DB_MIGRATE_*`. The `bin/*` scripts need no change.

Tests in `DatabaseCommandTest`:

- Extend the `command()` helper with an optional `migrationConfigLoader`, defaulting to the same `$this->config`, so existing tests keep passing.
- Add tests showing that `waitForDb` uses the app loader, and that `createDb`, `dropDb` and `migrate` use the migration loader. For example, give the two loaders configs with different users and assert which config reaches the server or migrator factory.
- Adjust `testConfigurationErrorsAreReported` so that it targets the loader `dropDb` actually uses.

## Files to Change

- `source/lib/Infra/Cli/DatabaseCommand.php`: second loader, wiring, docblocks.
- `source/tests/unit/Infra/Cli/DatabaseCommandTest.php`: helper parameter, plus routing tests.
