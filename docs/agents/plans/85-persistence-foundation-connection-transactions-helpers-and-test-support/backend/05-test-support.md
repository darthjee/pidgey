# Integration test support
Add the test support from `persistence/testing.md` and issue #85:

- `Pidgey\Tests\Support\RepositoryTestCase` (abstract, extends `IntegrationTestCase`): opens one PDO per test (`protected PDO $pdo`), `beginTransaction()` in `setUp()`, `rollBack()` in `tearDown()`.
- `Pidgey\Tests\Support\Fixtures`: constructed with a `PDO`; no table methods yet (added by #86–#89), plus a `protected`/`public` helper for unique values (e.g. `unique(string $prefix): string`) that later methods use.
- `Pidgey\Tests\Support\DatabaseCleaner::truncateAll(PDO $pdo)`: refuses (`LogicException`) unless `SELECT DATABASE()` ends in `_test`; lists tables from `information_schema.tables` (`table_schema = DATABASE()`, `table_type = 'BASE TABLE'`, `table_name <> 'schema_migrations'`); `SET FOREIGN_KEY_CHECKS = 0`, backtick-quoted `TRUNCATE` of each, and `SET FOREIGN_KEY_CHECKS = 1` in a `finally`.
- `source/tests/integration/Schema/SchemaTest.php`: asserts the set of base tables in `pidgey_test` equals the expected set (only `schema_migrations` for now), structured so #86–#89 add per-table expectations (columns, types, nullability, defaults, unique/foreign keys, collation).

Tests:
- `DatabaseCleaner`: empties application tables (create a scratch table inside a throwaway `_test` database via `ThrowawayDatabase`, insert, truncate), keeps `schema_migrations`, restores `FOREIGN_KEY_CHECKS` to 1 even when a truncate fails, refuses a database not ending in `_test`.
- Mixed isolation: a `RepositoryTestCase`-based test that runs after a `DatabaseCleaner`-based test in the same run still starts with an open transaction and sees an empty application schema.

## Files to Change
- `source/tests/support/RepositoryTestCase.php` — new.
- `source/tests/support/Fixtures.php` — new.
- `source/tests/support/DatabaseCleaner.php` — new.
- `source/tests/integration/Schema/SchemaTest.php` — new.
- `source/tests/integration/Support/DatabaseCleanerTest.php` — new.
- `source/tests/integration/Support/MixedIsolationTest.php` — new.
