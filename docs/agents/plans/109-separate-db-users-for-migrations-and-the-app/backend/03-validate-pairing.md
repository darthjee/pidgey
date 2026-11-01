# Validate the pairing in check-config

Add a `checkMigrationUser()` check to `ConfigValidator::validate()`, placed after `checkRequired()`. Using `Env::get` (so empty counts as unset):

- `DB_MIGRATE_USER` set, `DB_MIGRATE_PASSWORD` unset → `DB_MIGRATE_PASSWORD: missing`.
- `DB_MIGRATE_PASSWORD` set, `DB_MIGRATE_USER` unset → `DB_MIGRATE_USER: missing`.
- Both set, or neither set → no failure.

Never include either value in the line.

Tests:

- `ConfigValidatorTest`: add both keys to the cleanup list and cover the four combinations (data provider in the existing style).
- `CheckConfigTest` (integration): add one case in which `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` have distinctive values, one of them missing. Assert the failure line, and assert that neither distinctive value appears in the output, mirroring the existing `DB_USER` / `DB_PASSWORD` no-leak assertions.

## Files to Change

- `source/lib/Infra/ConfigValidator.php`: new check.
- `source/tests/unit/Infra/ConfigValidatorTest.php`: pairing cases.
- `source/tests/integration/Infra/CheckConfigTest.php`: failure line and no-leak case.
