# Development key and web boot refusal
Implement `architecture/configuration.md#development-key` and wire the web boot refusal.

- `ConfigValidator`:
  - add `public const DEVELOPMENT_SECRET_KEY = 'pidgey-development-only-insecure-secret-key-0000';`
  - add a `checkDevelopmentKey()` check, run **after** the missing / empty-entry / length checks (append it after `checkPreviousSecretKeys()` in `validate()`'s order, or at the end; keep the existing order of other failures). Exact byte comparison (`===`) against `SECRET_KEY` and each trimmed `PREVIOUS_SECRET_KEYS` entry. Skip when `isDevelopmentOrTest()`. Report `SECRET_KEY: development key` and `PREVIOUS_SECRET_KEYS: development key`, each at most once, never with a value.
- `FrontController::run()`: before building the router, run the configuration validation (inject it the same way `CheckConfigCommand` does, e.g. an optional `Closure(): list<string>` defaulting to `(new ConfigValidator())->validate(...)`, so tests can supply failures). On any failure throw a `RuntimeException('Invalid configuration: ' . implode(', ', $failures))`; the existing `catch (Throwable)` turns it into `500 INTERNAL_ERROR` and logs it. The PHP-version check stays in `bin/check-config` only.
- Tests:
  - `ConfigValidatorTest`: dev key in `SECRET_KEY` and in `PREVIOUS_SECRET_KEYS` (with surrounding spaces, several entries → one line) refused for `PHP_ENV` unset, empty, `production`, `Production`; allowed for `development` and `test`; a different 48-byte key passes; the failure lines contain no key.
  - `FrontControllerRunTest`: set a valid environment (`DB_HOST`, `DB_NAME`, `DB_USER`, `SECRET_KEY`) in the existing cases; add a case where the validation fails → `500 INTERNAL_ERROR` envelope whose body contains no key.
  - `CheckConfigTest` / `CheckConfigCommandTest`: a case for `SECRET_KEY: development key` outside development/test, if the existing structure makes it cheap.

## Files to Change
- `source/lib/Infra/ConfigValidator.php` — development-key constant and check.
- `source/lib/Http/FrontController.php` — boot validation in `run()`.
- `source/tests/unit/Infra/ConfigValidatorTest.php` — development-key cases.
- `source/tests/unit/Http/FrontControllerRunTest.php` — valid environment; refusal case.
- `source/tests/integration/Infra/CheckConfigTest.php` — development-key case (optional).
