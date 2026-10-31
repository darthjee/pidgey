# Backend Testing

Purpose: define backend test suites and quality tooling.

- **PHPUnit**, two suites:
  - `unit` (`source/tests/unit/`): no database, no network.
  - `integration` (`source/tests/integration/`): run against the dockerized MySQL
    (dedicated test database `pidgey_test`, migrations applied) and Mailpit (real SMTP sends,
    asserted through Mailpit's API — see [email/testing.md](../email/testing.md)).
- Coverage with pcov. Coverage is **report-only**: `tests` writes an HTML report to
  `source/coverage/`, and `coverage` writes Clover to `source/coverage/clover.xml`. No
  coverage threshold is enforced yet.

Test database isolation:

- `composer tests` and `composer tests:integration` run `@db:prepare` first (wait for
  MySQL, create the test database if missing, migrate); `composer tests:unit` does not, so
  unit tests run without MySQL. This wiring lives in composer, not in compose `command:`
  (which `docker compose run ... composer tests` and `make dev` bypass) nor in a PHPUnit
  bootstrap hook (too implicit).
- The integration bootstrap refuses to run unless `DB_NAME` ends in `_test`.
- Migrations-runner integration tests each create a throwaway database
  (`pidgey_runner_<random>_test`) and drop it in teardown, so `pidgey_test` only ever holds
  the real migrations. Fixture migration sets live in
  `source/tests/fixtures/migrations/<case>/` and are passed to the runner as its migrations
  directory.

Test folders under `source/tests/`:

| Folder | Content |
| --- | --- |
| `unit/` | Unit suite. |
| `integration/` | Integration suite. |
| `support/` | Shared test helpers. |
| `fixtures/` | Static test data. |

Composer scripts:

| Script | Action |
| --- | --- |
| `tests` | `@db:prepare`, then all PHPUnit suites (HTML coverage report). |
| `tests:unit` | Unit suite (no `db:prepare`, no MySQL needed). |
| `tests:integration` | `@db:prepare`, then the integration suite. |
| `db:wait` | `bin/wait-for-db`: wait until MySQL accepts connections. |
| `db:create` | `bin/create-db`: create `DB_NAME` if missing. |
| `db:drop` | `bin/drop-db`: drop `DB_NAME` if it exists. |
| `db:migrate` | `bin/migrate`: apply pending migrations. |
| `db:status` | `bin/migrate --status`: read-only migration status. |
| `db:prepare` | `@db:wait`, `@db:create`, `@db:migrate`. |
| `coverage` | PHPUnit with coverage report (pcov, Clover output used by CI). |
| `lint` | phpcs, then the phpmd unused-code gate. |
| `lint:fix` | phpcbf: auto-fix what phpcs can fix (a successful fix does not fail the script), then re-runs the phpmd unused-code gate. |
| `docs` | phpDocumentor via the `phpdoc` phar from the `dev_pidgey` image (not a Composer dependency; not available in CI — see [dependencies](../architecture/dependencies.md)). Output in `source/phpdoc/` (git-ignored). |
| `complexity` | phpmd complexity report (informational, not a gate). |

Linters:

- **phpcs**: PSR-12 plus slevomat/coding-standard rules; PHPDoc is required outside tests.
- **phpmd**: the unused-code ruleset is a gate in `lint`; the complexity rules produce a
  report through `complexity`.

## Configuration loading

Tests for the env-file loader and `bin/check-config` (see
[architecture/configuration.md](../architecture/configuration.md#env-file)):

- **Env-file loader, unit tests** (fixture files under `source/tests/fixtures/`):
  - parsing: `#` comments and blank lines are ignored, single and double surrounding quotes
    are stripped, the value is everything after the first `=`, and `$VAR` / `${VAR}` are kept
    literally (no expansion);
  - lines without `=`, lines whose key does not match `[A-Za-z_][A-Za-z0-9_]*`, and
    `export KEY=VALUE` lines are skipped;
  - `KEY=` sets the variable to an empty string;
  - a variable that is already set is never overridden, including one set to an empty string;
  - a missing file is a silent no-op.
- **Configuration validator, unit tests**: each boot-refusing rule in
  [architecture/configuration.md](../architecture/configuration.md) has a passing and a
  failing case, and each failure is reported under its check name (e.g. `SECRET_KEY: missing`,
  `SECRET_KEY: too short`, `PREVIOUS_SECRET_KEYS: empty entry`,
  `PREVIOUS_SECRET_KEYS: entry too short`, `DB_HOST: missing`,
  `<NAME>: not a positive integer`, `TRUSTED_PROXY_HOPS: not a non-negative integer`,
  `ACCESS_TOKEN_TTL: not less than REFRESH_TOKEN_TTL`), never with a value.
- **`bin/check-config` PHP version check, unit tests**: the PHP version is passed in rather
  than read from the running binary, so no second PHP binary is needed:
  - a version other than 8.4.x (e.g. `8.3.0`, `8.5.0`) fails with `PHP version: not 8.4.x`;
  - an 8.4.x version passes;
  - when no version is passed in, the command uses `PHP_VERSION`. There is no environment
    variable that overrides the version.
- **`bin/check-config`, integration tests** (run the command as a process):
  - a valid configuration exits `0`;
  - each invalid configuration (e.g. missing or short `SECRET_KEY`, a bad
    `PREVIOUS_SECRET_KEYS` entry, a missing required `DB_*`) exits non-zero and names the
    failed check;
  - an existing but unreadable `config/.env` exits non-zero and reports
    `env file: unreadable`, without printing the file's content;
  - the output never contains any configured value, on success or failure.

There is no automated test of the deploy workflow itself; how a release is verified is in
[deployment/release.md](../deployment/release.md#verification).
