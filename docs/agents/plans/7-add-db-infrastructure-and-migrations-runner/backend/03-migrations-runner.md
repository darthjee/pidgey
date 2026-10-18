# Migrations runner, lock and status

The runner applies validated migrations against a database, serialized by a named lock.

- `schema_migrations` table, created with `CREATE TABLE IF NOT EXISTS` by the runner:
  `version INT UNSIGNED PRIMARY KEY`, `name VARCHAR(255) NOT NULL`,
  `checksum CHAR(64) NOT NULL`, `applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)`;
  `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`.
- `Pidgey\Infra\Migrations\MigrationRunner` (constructor: `PDO $pdo`, `string $databaseName`,
  `string $directory`, `int $lockTimeout`, `MigrationLoader $loader`):
  - `migrate(): MigrationResult` —
    1. `SELECT GET_LOCK(:name, :timeout)` with name `pidgey_migrations:<databaseName>`
       truncated/hashed so it stays ≤ 64 characters. Result `1` → continue; `0`/`NULL` →
       throw "another migration run holds the lock on `<db>`" and apply nothing.
    2. Ensure `schema_migrations`, read applied rows, load files via the loader — all after the
       lock is held.
    3. Validate against applied state before running anything: applied version with no file →
       error; checksum mismatch → error naming the file; pending version lower than the highest
       applied version → error naming the file.
    4. Apply pending migrations in order: `exec` the single statement, then insert
       `(version, name, checksum)`. On a failing statement, stop, do not record it, and throw
       with the file path and the PDO error; later files are not attempted.
    5. `SELECT RELEASE_LOCK(:name)` in a `finally`, on success and failure alike.
  - `status(): list<MigrationStatus>` — read-only, takes **no** lock, tolerates a missing
    `schema_migrations` table (everything pending). Each entry: version, name, state
    `applied | pending | missing_file | checksum_mismatch | out_of_order`.
- `MigrationResult`: the list of applied migrations (empty list = no-op).
- Keep validation of applied-vs-files a pure function (e.g. `MigrationPlanner`) that both
  `migrate()` and `status()` use, so it is unit-testable without MySQL.
- Unit tests for the planner: nothing pending, pending only, missing file, checksum mismatch,
  out-of-order, gaps allowed. SQL-level behavior is covered in step 06.

## Files to Change
- `source/lib/Infra/Migrations/MigrationRunner.php` — new.
- `source/lib/Infra/Migrations/MigrationPlanner.php` — new (pure applied-vs-files validation).
- `source/lib/Infra/Migrations/MigrationResult.php` — new.
- `source/lib/Infra/Migrations/MigrationStatus.php` — new.
- `source/tests/unit/Infra/Migrations/MigrationPlannerTest.php` — new.
