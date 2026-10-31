# Issue: Configuration: env-file loader and bin/check-config

## Description
Part of epic #95. In production, the app's settings (`SECRET_KEY`, `DB_*`, `PHP_ENV`, `USER_APPROVAL_REQUIRED`, …) live in a server-only `.env` file, symlinked into each release at `config/.env`. This issue adds the env-file loader that reads it and the `bin/check-config` command that validates the configuration before a release goes live, as specified in [architecture/configuration.md](docs/agents/specs/architecture/configuration.md#env-file) (sections *Env file* and *`bin/check-config`*) and [testing/backend.md](docs/agents/specs/testing/backend.md#configuration-loading).

## Problem
- The app reads configuration only from environment variables (`Pidgey\Infra\Env`, `getenv`), and the production host can't set them for both the web (FastCGI) and the CLI (`bin/migrate` over SSH).
- Nothing validates the configuration today: no code reads `SECRET_KEY` or `PREVIOUS_SECRET_KEYS` yet, and there is no boot-time validator. A missing or incomplete `.env` would only show up once the new release is live.

## Expected Behavior
- **Env-file loader**
  - Runs before anything reads `Env`: at the start of `source/public/index.php` and of every `source/bin/*` script (`create-db`, `drop-db`, `migrate`, `wait-for-db`, `check-config`).
  - Reads `config/.env` relative to the app root (`source/` in the repo, the release root on the server).
  - Format and precedence exactly as in the spec: `#` comments and blank lines ignored; value is everything after the first `=`; surrounding single/double quotes stripped; no variable expansion; lines without `=` or with a key not matching `[A-Za-z_][A-Za-z0-9_]*` (including `export KEY=…`) skipped; `KEY=` sets an empty string; a variable already set (even to an empty string) is never overridden.
  - Missing file: silent no-op. Never prints or logs keys or values.
  - The rest of the app keeps using `Env`.
- **`source/bin/check-config`**
  - Loads the env file, then runs **every** boot-refusing rule from `configuration.md`, even for settings no code reads yet:
    - `SECRET_KEY` (present, at least 32 bytes) and `PREVIOUS_SECRET_KEYS` (no empty entry, each entry at least 32 bytes);
    - required `DB_HOST`, `DB_NAME`, `DB_USER`;
    - every duration and count is a positive integer when set; `TRUSTED_PROXY_HOPS` is a non-negative integer;
    - `ACCESS_TOKEN_TTL` is less than `REFRESH_TOKEN_TTL` (defaults applied);
    - the running PHP is 8.4.x.
  - Reports every failed check, not just the first.
  - Does not connect to the database.
  - Exit `0` when every check passes, `1` otherwise.
  - Names each failed check (`SECRET_KEY: too short`, `DB_HOST: missing`, `PHP version: not 8.4.x`, `env file: unreadable`), never a value, not even a partial one.
- `source/config/.env` is git-ignored.
- **Out of scope:** `public/index.php` only loads the env file; it does not run the validator yet. The web boot refusal is added with the auth work that first reads `SECRET_KEY`, so the dev setup and the health check are unaffected. Production is guarded by the pre-swap `check-config`.

## Solution
- **Loader:** a small class in `Pidgey\Infra` (e.g. `EnvFile`) with a pure parser and a loader that calls `putenv()` for unset keys. Each entry point calls it right after `vendor/autoload.php`.
- **Validation:** a configuration validator returning the list of failed checks (names only), used by `check-config`.
- **Command:** a `Pidgey\Infra\Cli` command class, following `DatabaseCommand`. It takes the PHP version as a constructor argument (default `PHP_VERSION`); there is no environment override in production.
- **Tests** (per testing/backend.md):
  - unit tests for the parser and loader, with fixture files under `source/tests/fixtures/`;
  - unit tests for the validator (every rule) and for the command's PHP version check, injecting e.g. `8.3.0` and `8.5.0`;
  - integration tests that run `bin/check-config` as a process: valid config, invalid cases, unreadable file, and no configured value ever in the output (on success or failure).
- **Spec touch-up:** move the PHP version case in `testing/backend.md` from the process tests to the unit tests, and note in `configuration.md` that the web boot refusal is not wired yet (product-owner).
- **Owner:** backend agent.

## Benefits
- The same code reads the configuration on the web and in the CLI, in production and in development.
- A broken or incomplete production `.env` aborts the deploy in staging, before migrations and the swap, instead of breaking the live site.
- Secrets never reach CI logs.
