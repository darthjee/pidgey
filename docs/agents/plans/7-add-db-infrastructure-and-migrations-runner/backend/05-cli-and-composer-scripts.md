# CLI entry points and composer scripts

Thin executable entry points plus the composer front door.

- `source/bin/wait-for-db`, `create-db`, `drop-db`, `migrate`: `#!/usr/bin/env php`, executable
  (`chmod +x`), `declare(strict_types=1);`, require `__DIR__ . '/../vendor/autoload.php'`, build
  `DatabaseConfig::fromEnv()` and delegate to a small `Pidgey\Infra\Cli\*` command class each
  (or one `Pidgey\Infra\Cli\DatabaseCommand` with one method per command) that returns an exit
  code and writes to injected STDOUT/STDERR streams — keeping logic out of the scripts and
  testable.
  - `wait-for-db`: timeout from `DB_WAIT_TIMEOUT` (default 60).
  - `create-db` / `drop-db`: act on `DB_NAME`.
  - `migrate`: migrations dir `__DIR__ . '/../migrations'`, lock timeout from
    `MIGRATE_LOCK_TIMEOUT` (default 60); prints each applied file, or "Nothing to migrate.".
    `--status` prints a table of version / name / state. Unknown arguments → usage on stderr,
    exit 2.
  - Any exception → message on stderr, exit 1.
- `source/composer.json`:
  - `scripts`: add `db:wait`, `db:create`, `db:drop`, `db:migrate`, `db:status`, `db:prepare`
    (`["@db:wait", "@db:create", "@db:migrate"]`); change `tests` to
    `["@db:prepare", "phpunit --coverage-html coverage"]` and `tests:integration` to
    `["@db:prepare", "phpunit --testsuite integration"]`; leave `tests:unit` and `coverage`
    unchanged.
  - `scripts-descriptions` for every new script, and update `tests`/`tests:integration`
    descriptions to mention the DB preparation.
  - `autoload-dev`: `"Pidgey\\Tests\\": "tests/"` so `tests/support` classes autoload.
  - Lint paths: add `bin` to the phpmd path list in `lint` and `lint:fix`.
- `source/phpcs.xml`: add `<file>bin</file>`.
- Unit tests for the command class(es): exit codes, stdout/stderr output, `--status`
  formatting, unknown-argument handling (with fake runner/server).

## Files to Change
- `source/bin/wait-for-db`, `source/bin/create-db`, `source/bin/drop-db`, `source/bin/migrate` — new.
- `source/lib/Infra/Cli/*.php` — new command class(es).
- `source/tests/unit/Infra/Cli/*Test.php` — new.
- `source/composer.json` — new scripts, descriptions, `autoload-dev`, lint paths.
- `source/phpcs.xml` — lint `bin/`.
