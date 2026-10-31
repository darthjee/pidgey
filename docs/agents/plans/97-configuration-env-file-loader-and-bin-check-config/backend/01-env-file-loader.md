# Env-file loader
Add `Pidgey\Infra\EnvFile` (or a similar name), with two parts:

- **A pure parser**, `parse(string $contents): array<string, string>`, following [configuration.md § Env file](../../../specs/architecture/configuration.md#env-file):
  - Skip blank lines and lines starting with `#` (after trimming leading whitespace).
  - Split on the first `=`. The key is trimmed and must match `^[A-Za-z_][A-Za-z0-9_]*$`; otherwise skip the line. This also skips `export KEY=…`.
  - The value is everything after the first `=`, with surrounding whitespace trimmed. One matching pair of surrounding `"` or `'` is stripped.
  - There is no expansion, and `KEY=` gives `''`.
- **A loader**, `load(string $path): void`:
  - When `$path` doesn't exist, do nothing.
  - When it exists but can't be read, throw a dedicated exception (e.g. `EnvFileUnreadable`) whose message holds no content. The web entry point can ignore or rethrow it; `check-config` turns it into `env file: unreadable`.
  - Otherwise, for each parsed key with `getenv($key) === false` (unset; an empty string counts as set), call `putenv("$key=$value")`.
  - Never print or log anything.

Wire it into every entry point, right after `require __DIR__ . '/../vendor/autoload.php';`, through `EnvFile::load(__DIR__ . '/../config/.env')`:

- `source/public/index.php`;
- `source/bin/create-db`, `drop-db`, `migrate`, `wait-for-db`.

`bin/check-config` comes in step 03.

Git-ignore `source/config/.env` in the root `.gitignore`. Don't create `source/config/`; it stays absent in development (the spec says docker, CI and tests have no `config/.env`).

Unit tests (`source/tests/unit/Infra/EnvFileTest.php`) with fixtures under `source/tests/fixtures/env/`:

- the parser cases from `testing/backend.md` § *Configuration loading*;
- `load()`:
  - fills an unset variable;
  - never overrides a set variable, including one set to `''`;
  - does nothing for a missing file;
  - throws for an unreadable file. Create a temporary file with `chmod 000`, and skip the test when running as root, since root can read it anyway.
- Clean up with `putenv('KEY')` in `tearDown`. Use unique key names (e.g. a `PIDGEY_TEST_` prefix) so the tests don't affect each other.

## Files to Change
- `source/lib/Infra/EnvFile.php`: new parser and loader.
- `source/lib/Infra/EnvFileUnreadable.php`: new exception, or a nested/generic one, following the existing exception conventions.
- `source/public/index.php`, `source/bin/create-db`, `source/bin/drop-db`, `source/bin/migrate`, `source/bin/wait-for-db`: load `config/.env`.
- `.gitignore`: `source/config/.env`.
- `source/tests/unit/Infra/EnvFileTest.php`, `source/tests/fixtures/env/*`: tests.
