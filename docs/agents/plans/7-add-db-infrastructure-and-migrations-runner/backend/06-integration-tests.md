# Integration test support and runner integration tests

Integration tests against the dockerized MySQL, isolated from `pidgey_test`'s real schema.

- `Pidgey\Tests\Support\IntegrationTestCase` (abstract, extends `TestCase`):
  - `setUpBeforeClass()`: refuse to run (fail with a clear message) unless `DB_NAME` ends in
    `_test` — the safety guard. Lives here (not in a global PHPUnit bootstrap) so the unit suite
    never needs DB env.
  - Helpers: `config()`, `pdo()` for `pidgey_test`.
- `Pidgey\Tests\Support\ThrowawayDatabase`: creates `pidgey_runner_<random hex>_test` via
  `DatabaseServer::createDatabase()`, exposes a PDO for it, drops it in teardown (also on
  failure).
- `source/tests/integration/Infra/Migrations/MigrationRunnerTest.php` (each test on its own
  throwaway DB, migrations dir pointed at `source/tests/fixtures/migrations/<case>/`):
  - Fresh DB: `valid` applies all files, records versions, names and checksums.
  - Running migrate twice: second run is a no-op; schema and `schema_migrations` unchanged.
  - A failing migration (`failing` fixture: valid `0001`, invalid-SQL `0002`, valid `0003`):
    `0001` recorded, `0002` not recorded, `0003` not attempted, exception names `0002`.
  - Edited applied file (copy `valid` to a temp dir, migrate, edit a file): migrate fails, no
    new rows; `status()` reports `checksum_mismatch`.
  - Missing applied file and out-of-order pending file (temp dir manipulation): migrate fails
    before applying anything; `status()` reports `missing_file` / `out_of_order`.
  - Every loader validation case from step 02 (duplicates, bad name, empty, multi-statement,
    missing dir) leaves the DB untouched (no `schema_migrations` rows).
  - Lock: hold `GET_LOCK` for the throwaway DB's lock name from a second PDO connection; a runner
    with `lockTimeout = 1` throws the "holds the lock" error and applies nothing.
  - `status()` on a DB with no `schema_migrations` table: all pending, no table created.
- `source/tests/integration/Infra/DatabaseServerTest.php`: create/drop a throwaway DB as
  `DB_USER` (proves the `pidgey\_%` grant), `waitUntilReady` returns against the live server.
- `source/tests/integration/Infra/PdoFactoryTest.php`: a multi-statement query
  (`SELECT 1; SELECT 2`) is rejected; charset is `utf8mb4`.
- Remove `source/tests/integration/.gitkeep`, `tests/support/.gitkeep` once real files exist.

## Files to Change
- `source/tests/support/IntegrationTestCase.php` — new.
- `source/tests/support/ThrowawayDatabase.php` — new.
- `source/tests/integration/Infra/Migrations/MigrationRunnerTest.php` — new.
- `source/tests/integration/Infra/DatabaseServerTest.php` — new.
- `source/tests/integration/Infra/PdoFactoryTest.php` — new.
- `source/tests/fixtures/migrations/failing/*.sql` — new fixture set.
- `source/tests/integration/.gitkeep`, `source/tests/support/.gitkeep` — remove.
