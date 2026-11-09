# SecretString and runtime hardening

Add `Pidgey\Infra\SecretString` and `Pidgey\Infra\Runtime` per the shared contracts.

- `SecretString`: private string property; `reveal()`, `wipe()` (`sodium_memzero()`, then
  a `wiped` flag; `reveal()` after it throws `LogicException`), `__debugInfo()` redacted,
  `__serialize()` / `__unserialize()` / `__clone()` throw. No `__toString`, no
  `JsonSerializable`. Constructor parameter marked `#[SensitiveParameter]`.
- `Runtime::harden()`: reads `PHP_ENV` through `Env::get`; outside `development` / `test`
  sets `zend.exception_ignore_args` to `1`. Reuse the same environment list as
  `ConfigValidator` (extract it to a public constant if needed, do not duplicate the list).
- Call `Runtime::harden()` right after `EnvFile::load()` in `public/index.php` and the five
  `bin/*` scripts that load `.env` themselves; inside `CheckConfigCommand::run()` right
  after its own `EnvFile::load()`.

Tests:

- `tests/unit/Infra/SecretStringTest.php`: `reveal()`; `(string)` cast throws `Error`;
  `serialize()`, `unserialize()`-style restore and `clone` throw; `print_r` / `var_dump`
  output contains `[redacted]` and not the value; `json_encode()` gives `{}`; `wipe()` then
  `reveal()` throws; a Monolog context through `CapturedLog` logs the class name only
  (`assertNotLogged` with the canary value).
- `tests/unit/Infra/RuntimeTest.php`: `production`, unset, `development`, `test` (restore
  the ini value and `PHP_ENV` in teardown).
- `tests/unit/Infra/EntryPointsTest.php`: scans `public/index.php` and every file in `bin/`;
  each one that calls `EnvFile::load` (or delegates to `CheckConfigCommand`) must call
  `Runtime::harden()`; fails naming the file.

## Files to Change

- `source/lib/Infra/SecretString.php` — new.
- `source/lib/Infra/Runtime.php` — new.
- `source/lib/Infra/ConfigValidator.php` — expose the non-production environment list if
  `Runtime` needs it.
- `source/public/index.php`, `source/bin/create-db`, `source/bin/drop-db`,
  `source/bin/migrate`, `source/bin/reencrypt-smtp-passwords`, `source/bin/wait-for-db` —
  call `Runtime::harden()`.
- `source/lib/Infra/Cli/CheckConfigCommand.php` — call `Runtime::harden()` after loading.
- `source/tests/unit/Infra/SecretStringTest.php`, `RuntimeTest.php`, `EntryPointsTest.php`
  — new.
