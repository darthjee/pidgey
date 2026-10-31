# bin/check-config command
Add `Pidgey\Infra\Cli\CheckConfigCommand`, following `DatabaseCommand`'s style (exit-code constants, injected stdout/stderr streams):

- Constructor: the env-file path, the validator (or a closure), the PHP version string (default `PHP_VERSION`), stdout, stderr.
- `run(): int`:
  1. Load the env file through `EnvFile::load()`. On `EnvFileUnreadable`, record `env file: unreadable` and keep going, so the other checks still report.
  2. Run the validator.
  3. If the PHP version doesn't start with `8.4.`, record `PHP version: not 8.4.x`.
  4. With failures, write each one on its own line to stderr and return `1`. Otherwise write `configuration OK` to stdout and return `0`.
- It does not connect to the database.

Add `source/bin/check-config`, executable (`chmod +x`), with the same header as the other `bin/*` scripts. It must not call `EnvFile::load` before the command: the command loads the file itself, so it can report an unreadable file. It ends with:

```php
exit((new CheckConfigCommand(__DIR__ . '/../config/.env', ..., STDOUT, STDERR))->run());
```

Tests:

- **Unit** (`source/tests/unit/Infra/Cli/CheckConfigCommandTest.php`), using `php://memory` streams:
  - the PHP version: `8.4.0` and `8.4.27` pass, `8.3.0` and `8.5.0` fail;
  - an unreadable file reports `env file: unreadable` (skipped as root);
  - success output;
  - failure lines.
- **Integration** (`source/tests/integration/Infra/CheckConfigTest.php`), running the real script with `proc_open` and an explicit, minimal environment (no inherited `DB_*` or `SECRET_KEY`):
  - Build a temporary app root:
    - `<tmp>/bin/check-config`, a copy of the script;
    - `<tmp>/vendor`, a symlink to `source/vendor`;
    - `<tmp>/config/.env`, written by each test.
  - This way the tests never write to `source/config/`.
  - Cases:
    - a valid config through the env file exits `0`;
    - a valid config through the process environment, with no file, exits `0`;
    - the process environment wins over the file;
    - a missing `SECRET_KEY`, a short `SECRET_KEY`, a bad `PREVIOUS_SECRET_KEYS` entry and a missing `DB_HOST` each exit `1` and name the check;
    - an unreadable `.env` exits `1` with `env file: unreadable` (skipped as root);
    - on success and on failure, stdout and stderr contain none of the configured values. Assert this with distinctive secret strings.
  - The PHP version case is unit-only, as decided in the issue. The process runs with `PHP_BINARY`, which is 8.4 in CI and docker.

## Files to Change
- `source/lib/Infra/Cli/CheckConfigCommand.php`: new command.
- `source/bin/check-config`: new executable entry point.
- `source/tests/unit/Infra/Cli/CheckConfigCommandTest.php`: unit tests.
- `source/tests/integration/Infra/CheckConfigTest.php`: process tests.
