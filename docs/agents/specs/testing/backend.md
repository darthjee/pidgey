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
  - a variable that is already set is never overridden;
  - a missing file is a silent no-op.
- **`bin/check-config`, integration tests** (run the command as a process):
  - a valid configuration exits `0`;
  - each invalid configuration (e.g. missing or short `SECRET_KEY`, a bad
    `PREVIOUS_SECRET_KEYS` entry, a missing required `DB_*`) exits non-zero and names the
    failed check;
  - a PHP version other than 8.4.x exits non-zero (the version check must be testable without
    a second PHP binary, e.g. by injecting the version);
  - the output never contains any configured value, on success or failure.

There is no automated test of the deploy workflow itself; how a release is verified is in
[deployment/release.md](../deployment/release.md#verification).
