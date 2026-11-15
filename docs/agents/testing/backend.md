# Backend Testing

Purpose: define backend test suites and quality tooling.

- **PHPUnit**, two suites:
  - `unit` (`source/tests/unit/`): no database, no network.
  - `integration` (`source/tests/integration/`): run against the dockerized MySQL
    (dedicated test database `pidgey_test`, migrations applied) and the three test-only
    Mailpit instances `mailpit-plain`, `mailpit-starttls` and `mailpit-ssl` (real SMTP
    sends, asserted through Mailpit's API — see
    [email/testing.md](../email/testing.md#mailpit-instances)). The integration suite never
    uses the development `mailpit`.
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

## Email test support

Test-only helpers for the email integration (behavior in
[email/testing.md](../email/testing.md)):

| Class / fixture | Location | Role |
| --- | --- | --- |
| `Pidgey\Tests\Support\Mailpit` | `support/` | Mailpit HTTP API client: clear (`DELETE /api/v1/messages`), list, read. The only reader of the `TEST_MAILPIT_*` keys. |
| `Pidgey\Tests\Support\FakeSmtpStream` | `support/` | In-memory, scripted implementation of `Pidgey\Smtp\SmtpStream`, for unit tests; delays advance a shared `FrozenClock` (time model in [email/testing.md](../email/testing.md#pidgeytestssupportfakesmtpstream)). |
| `Pidgey\Tests\Support\FakeSmtpServer` | `support/` | Starts the fake SMTP server script as a child process with a JSON scenario, reads its `PORT=` line, stops it in teardown and asserts its exit status and empty stderr. |
| `Pidgey\Tests\Support\FrozenClock` | `support/` | A monotonic `Clock` whose `sleep()` advances `now()` instead of waiting, for the timeouts and the session deadline. |
| `Pidgey\Tests\Support\FrozenWallClock` | `support/` | A UTC `WallClock` frozen at a given instant, moved forward with `advance()`, for the test-email limit window. |
| `Pidgey\Tests\Support\CapturedLog` | `support/` | A logger built through `LoggerFactory::create('debug', TestHandler)` and injected with `new Container(logger: …)`; exposes the records and formatted lines, and `assertNotLogged()` fails if a line holds the secret in plain or base64 form. For the canary-password assertions. |
| Fake SMTP server script | `support/scripts/fake-smtp-server.php` | `stream_socket_server` script, run through `proc_open`: serves one session from a JSON scenario (format in [email/testing.md](../email/testing.md#pidgeytestssupportfakesmtpserver)). |
| Lock holder script | `fixtures/scripts/organization-lock-holder.php` | Child process that holds an organization lock, for the concurrent `activate` and `activate` / `deactivate` tests. Arguments: organization id, hold time in milliseconds, optionally a connection id to activate before commit. It opens a transaction, prints `LOCKED` once `SELECT … FROM organizations WHERE id = ? FOR UPDATE` returns, holds the lock for the given time, activates the connection when one is given, commits and exits `0`. |
| TLS material | `fixtures/tls/` | Test CA, leaf certificates and the regeneration script ([TLS fixtures](../email/testing.md#tls-fixtures)). |

Guard tests (unit suite, details in
[email/testing.md](../email/testing.md#guard-tests)):

- a scan of `source/lib` and `source/tests` that fails on disabled TLS verification
  (`'verify_peer' => false`, `'verify_peer_name' => false`, `allow_self_signed`);
- the `SecretString` leak checks (cast, serialization, clone, dumps, JSON, Monolog context);
- a scan of `public/index.php` and `bin/*` that fails when an entry point does not call
  `Pidgey\Infra\Runtime::harden()`.

Test-only keys:

- `TEST_MAILPIT_PLAIN`, `TEST_MAILPIT_STARTTLS` and `TEST_MAILPIT_SSL`, each of the form
  `host:smtpPort:apiPort` (values in
  [email/testing.md](../email/testing.md#reaching-the-instances)).
- Read only by `Pidgey\Tests\Support\Mailpit`, never by the application or
  `ConfigValidator`. A missing key fails the test; it is never skipped.

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
  `PREVIOUS_SECRET_KEYS: entry too short`, `SECRET_KEY: development key`,
  `PREVIOUS_SECRET_KEYS: development key`, `DB_HOST: missing`,
  `<NAME>: not a positive integer`, `TRUSTED_PROXY_HOPS: not a non-negative integer`,
  `ACCESS_TOKEN_TTL: not less than REFRESH_TOKEN_TTL`, `ALLOWED_ORIGINS: invalid origin`,
  `ALLOWED_ORIGINS: not https`), never with a value. The
  [development-key rule](../architecture/configuration.md#development-key) has these cases:
  - the development key as `SECRET_KEY` under `PHP_ENV=production`, under an unset
    `PHP_ENV` and under `PHP_ENV=Production` → `SECRET_KEY: development key`;
  - the development key as one `PREVIOUS_SECRET_KEYS` entry (with surrounding whitespace)
    under a non-development `PHP_ENV` → `PREVIOUS_SECRET_KEYS: development key`;
  - the development key under `PHP_ENV=development` and under `PHP_ENV=test` → passes;
  - a different key of the same length under `PHP_ENV=production` → passes.
  The [`ALLOWED_ORIGINS` rules](../architecture/configuration.md#origins) have these cases:
  - a valid `https` entry (with and without a port, with surrounding whitespace and empty
    entries) under `PHP_ENV=production` → passes;
  - an entry with a path, or with only a trailing `/` → `ALLOWED_ORIGINS: invalid origin`;
  - an entry without a scheme (`admin.example.com`) → `ALLOWED_ORIGINS: invalid origin`;
  - an `ftp://` entry → `ALLOWED_ORIGINS: invalid origin`;
  - an `http` entry under `PHP_ENV=production` → `ALLOWED_ORIGINS: not https`;
  - an `http` entry under `PHP_ENV=development` and under `PHP_ENV=test` → passes;
  - an `http` entry under an unset `PHP_ENV` → `ALLOWED_ORIGINS: not https`;
  - several failing entries → each failure reported once.
- **`bin/check-config` PHP version check, unit tests**: the PHP version is passed in rather
  than read from the running binary, so no second PHP binary is needed:
  - a version other than 8.4.x (e.g. `8.3.0`, `8.5.0`) fails with `PHP version: not 8.4.x`;
  - an 8.4.x version passes;
  - when no version is passed in, the command uses `PHP_VERSION`. There is no environment
    variable that overrides the version.
- **`bin/check-config`, integration tests** (run the command as a process):
  - a valid configuration exits `0`;
  - each invalid configuration (e.g. missing or short `SECRET_KEY`, a bad
    `PREVIOUS_SECRET_KEYS` entry, a missing required `DB_*`, the development key with a
    non-development `PHP_ENV`) exits non-zero and names the failed check;
  - an existing but unreadable `config/.env` exits non-zero and reports
    `env file: unreadable`, without printing the file's content;
  - the output never contains any configured value, on success or failure.

There is no automated test of the deploy workflow itself; how a release is verified is in
[deployment/operations.md](../../deployment/operations.md#release).
