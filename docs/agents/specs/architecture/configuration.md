# Configuration

Purpose: list every environment variable the application reads, with its default and effect.

## Rules

- Configuration comes from environment variables, read through `Pidgey\Infra\Env`.
  Development values live in the `.env` file used by docker-compose (see
  [docker/compose.md](../docker/compose.md)). In production, they come from an env file
  loaded at startup (see [Env file](#env-file)).
- Names are **bare** (no application prefix), matching the existing `DB_*` and `SMTP_*`
  variables.
- Durations are integers in **seconds**. A duration must be a **positive integer**; any other
  value (zero, negative, non-numeric) refuses boot (or the command exits non-zero).
- Counts (e.g. `ACCOUNT_EDIT_MAX_ATTEMPTS`) must be **positive integers**; any other value
  refuses boot (or the command exits non-zero). **Exception:** `TRUSTED_PROXY_HOPS` must be a
  **non-negative** integer (`0` is allowed); any other value refuses boot.
- Booleans are enabled only by the exact value `true`; anything else is `false`.
  **Exception:** `USER_APPROVAL_REQUIRED` is disabled only by the exact value `false`; anything
  else (unset, empty, a typo) keeps approval required, so a typo never silently opens
  registration.
- "required" means the app refuses to start (or the command exits non-zero) when the value is
  missing.

## Settings

### Runtime and database

| Setting | Default | Effect |
| --- | --- | --- |
| `PHP_ENV` | none | Runtime environment (`development`, `test`, `production`); set by docker-compose. Any value other than exactly `development` or `test` (including unset, empty or a different case such as `Production`) is treated as production by the [development-key rule](#development-key) and the [`ALLOWED_ORIGINS` https rule](#origins). |
| `DB_HOST` | required | MySQL host. |
| `DB_PORT` | `3306` | MySQL port. |
| `DB_NAME` | required | Database name. |
| `DB_USER` | required | Database user of the web app and `bin/wait-for-db`; also used by the schema/admin commands when `DB_MIGRATE_USER` is unset. |
| `DB_PASSWORD` | empty | Database password. |
| `DB_MIGRATE_USER` | none (falls back to `DB_USER`) | Database user for the schema/admin commands `bin/migrate` (including `--status`), `bin/create-db` and `bin/drop-db` (see [database.md](database.md#database-users)). Must be set together with `DB_MIGRATE_PASSWORD`. |
| `DB_MIGRATE_PASSWORD` | none (falls back to `DB_PASSWORD` when `DB_MIGRATE_USER` is unset) | Password of `DB_MIGRATE_USER`. Must be set together with `DB_MIGRATE_USER`. |
| `DB_WAIT_TIMEOUT` | `60` | Seconds the database CLI waits for MySQL to accept connections. |
| `MIGRATE_LOCK_TIMEOUT` | `60` | Seconds a migration run waits for the migration lock (see [database.md](database.md)). |

- `DB_HOST`, `DB_PORT` and `DB_NAME` are shared by both users.
- `DB_MIGRATE_USER` and `DB_MIGRATE_PASSWORD` are set **together or not at all**. The rule is
  enforced by [`bin/check-config`](#bin-check-config), which runs before every
  release: when one is set and the other is not (an empty value counts as unset, like every
  other key), it exits non-zero. The schema/admin commands themselves do not enforce it: they
  use the migration user whenever `DB_MIGRATE_USER` is set (with an empty password if
  `DB_MIGRATE_PASSWORD` is unset) and otherwise fall back to `DB_USER` / `DB_PASSWORD`, so a
  single-user setup (Docker, CI, tests) needs no change.
- `bin/wait-for-db` and the web app always use `DB_USER` / `DB_PASSWORD`, whether or not the
  `DB_MIGRATE_*` keys are set.

### Test-only keys

There is no application-level SMTP host or port: organizations' SMTP connections are stored
in the database (see [email/smtp-connections.md](../email/smtp-connections.md)).

The test environment sets `TEST_MAILPIT_PLAIN`, `TEST_MAILPIT_STARTTLS` and
`TEST_MAILPIT_SSL` (form `host:smtpPort:apiPort`). They are **not** application settings:
only the test support `Pidgey\Tests\Support\Mailpit` reads them, never the application or
`ConfigValidator`, and production never sets them. Compose `tests` sets
`mailpit-plain:2025:9025`, `mailpit-starttls:2587:9587` and `mailpit-ssl:2465:9465`; CI
`backend_tests` sets `localhost:2025:9025`, `localhost:2587:9587` and `localhost:2465:9465`.
See
[email/testing.md](../email/testing.md#reaching-the-instances).

### SMTP password encryption

Used by [email/password-encryption.md](../email/password-encryption.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `SMTP_ENCRYPTION_KEY` | required | Encrypts and decrypts the stored SMTP connection passwords. A dedicated key, not derived from `SECRET_KEY`. See [SMTP encryption keys](#smtp-encryption-keys). |
| `PREVIOUS_SMTP_ENCRYPTION_KEYS` | empty | Comma-separated previous keys, used **only to decrypt** passwords during a key rotation (see [password-encryption.md](../email/password-encryption.md#rotation) and [SMTP encryption keys](#smtp-encryption-keys)). |

### SMTP transport

Used by the SMTP session of [email/send-flow.md](../email/send-flow.md) (test email and
`POST /v1/messages`).

| Setting | Default | Effect |
| --- | --- | --- |
| `SMTP_CONNECT_TIMEOUT` | `10` | Seconds for the TCP connect, the `ssl` handshake and the `220` greeting (see [send-flow.md](../email/send-flow.md#timeouts)). |
| `SMTP_COMMAND_TIMEOUT` | `10` | Seconds for each command's reply, including the `STARTTLS` handshake and the reply to the final `.` of `DATA`. |
| `SMTP_TOTAL_TIMEOUT` | `25` | Seconds for the whole SMTP session: a deadline fixed at connect time; every operation waits `min(its own timeout, time remaining)`. Must stay below the web server / proxy request timeout (see the deployment docs). |
| `SMTP_CA_FILE` | empty | Path to a PEM CA bundle that **replaces** the system CA store for SMTP TLS verification (to trust both, concatenate them). Empty: the system store (`openssl.cafile` / `openssl.capath` / the OpenSSL default). The test environment (compose `tests` and CI `backend_tests`) sets `SMTP_CA_FILE=tests/fixtures/tls/ca.pem`, the test CA (see [email/smtp-connections.md](../email/smtp-connections.md#tls-policy) and [email/testing.md](../email/testing.md#tls-fixtures)). |
| `SMTP_ALLOW_PRIVATE_HOSTS` | `false` | When `true`, the send-time [host check](../email/send-flow.md#host-check) denies no address, so internal relays and Mailpit are reachable. When `false` (any value other than exactly `true`, see [Rules](#rules)), a host that resolves to a loopback, private, link-local, shared, unspecified, multicast or reserved address is refused with `409 SMTP_HOST_NOT_ALLOWED`. The development `.env` and the test environment (compose `tests` and CI `backend_tests`) set `true`; production keeps the default unless a self-hosted setup needs an internal relay. |
| `SMTP_TEST_MAX_ATTEMPTS` | `10` | Test emails allowed per organization within one `SMTP_TEST_WINDOW` (a count, see [Rules](#rules); see [test-email limit](../api/endpoints/pidgey-admin/smtp-connections.md#test-email-limit)). |
| `SMTP_TEST_WINDOW` | `3600` (1 h) | Length of the fixed test-email window, in seconds (a duration). |
| `SMTP_EHLO_NAME` | empty | The client name sent as the `EHLO` argument; recommended: the host's public FQDN, ideally matching its reverse DNS. Empty: the address literal of the socket's local address (e.g. `[203.0.113.7]`, see [send-flow.md](../email/send-flow.md#ehlo)). |

- The three timeouts and `SMTP_TEST_WINDOW` are durations and `SMTP_TEST_MAX_ATTEMPTS` is a
  count (positive integers, see [Rules](#rules)); an invalid value refuses boot with
  `<NAME>: not a positive integer`. `SMTP_ALLOW_PRIVATE_HOSTS` is a boolean and never
  refuses boot.
  `SMTP_CONNECT_TIMEOUT` and `SMTP_COMMAND_TIMEOUT` must each be `<= SMTP_TOTAL_TIMEOUT`;
  otherwise boot is refused.
- **Timeout precedence:** a timeout that is not a positive integer reports only
  `<NAME>: not a positive integer` and is skipped by the `exceeds SMTP_TOTAL_TIMEOUT`
  check; an invalid `SMTP_TOTAL_TIMEOUT` skips both `exceeds` checks. The comparison uses
  the **effective** values (defaults applied): `SMTP_TOTAL_TIMEOUT=5` with
  `SMTP_CONNECT_TIMEOUT` unset fails with `SMTP_CONNECT_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT`.
- `SMTP_CA_FILE`, when set, must name an existing, readable file; otherwise boot is
  refused. Its contents are not parsed at boot. A **relative** path is resolved against the
  app root (`source/`), never the working directory (which differs between web and CLI),
  by both the validation and the transport; an absolute path is used as is. So the test
  value `tests/fixtures/tls/ca.pem` is `source/tests/fixtures/tls/ca.pem`.
- `SMTP_EHLO_NAME`, when set, must be an ASCII LDH hostname (letters, digits, hyphen; labels
  of 1–63 characters with no leading or trailing hyphen, at most 253 characters in total);
  otherwise boot is refused.
- **Failure names** (each reported at most once, never with a value):
  `SMTP_CONNECT_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT`,
  `SMTP_COMMAND_TIMEOUT: exceeds SMTP_TOTAL_TIMEOUT`, `SMTP_CA_FILE: not readable`,
  `SMTP_EHLO_NAME: invalid hostname`.
- **Session settings:** the SMTP layer never reads `Env`. `Pidgey\Container::smtpSessionSettings()`
  builds a `Pidgey\Smtp\SessionSettings` once (memoized) from these keys: the connect,
  command and total timeouts (in seconds, defaults applied from the `ConfigValidator`
  constants `DEFAULT_SMTP_CONNECT_TIMEOUT`, `DEFAULT_SMTP_COMMAND_TIMEOUT`,
  `DEFAULT_SMTP_TOTAL_TIMEOUT`), the CA file (absolute, or none when empty) and the `EHLO`
  name (or none when empty). The transport and the send flow receive it built.
- **Stack-trace arguments:** outside `development` / `test` (same `PHP_ENV` rule as the
  [development key](#development-key); an unset `PHP_ENV` counts as production),
  `Pidgey\Infra\Runtime::harden()` runs `ini_set('zend.exception_ignore_args', '1')` (the
  setting is `INI_ALL`), so no stack trace carries arguments (and no secret, see
  [send-flow.md](../email/send-flow.md#secret-hygiene)) regardless of the host's `php.ini`.
  There is no shared boot: `public/index.php` and every `bin/*` entry point call it right
  after loading the env file (a command that loads the env file itself, such as
  `bin/check-config`, calls it right after that load). A test fails when an entry point
  does not call it. This is not a setting and `bin/check-config` does not check it.

### Logging

Used by the operator log
([api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md#operator-log)) and the
request log ([request-handler/front-controller.md](../request-handler/front-controller.md)),
both written through the Monolog logger built by `Pidgey\Infra\LoggerFactory` (see
[backend.md](backend.md#logging)).

| Setting | Default | Effect |
| --- | --- | --- |
| `LOG_LEVEL` | `info` | Minimum level written to the log: one of the PSR-3 level names `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency` (lower case, exact). |

- Any other value refuses boot with `LOG_LEVEL: invalid level` (reported once, never with
  the value).

### Authentication

Used by [api/authentication.md](../api/authentication.md) and, for the `AUTHORIZATION_REQUEST_*`
settings, by [api/endpoints/auth/device-authorization.md](../api/endpoints/auth/device-authorization.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `SECRET_KEY` | required | Signs access-token JWTs (HS256). See [Secret keys](#secret-keys). |
| `PREVIOUS_SECRET_KEYS` | empty | Comma-separated retired keys, used **only to verify** access tokens during a key rotation (see [access-token.md](../api/authentication/access-token.md#key-rotation) and [Secret keys](#secret-keys)). |
| `ACCESS_TOKEN_TTL` | `900` | JWT `exp` and the `access_token` cookie `Max-Age`, in seconds. Must be less than `REFRESH_TOKEN_TTL`, otherwise boot is refused. |
| `REFRESH_TOKEN_TTL` | `604800` (7 days) | Refresh-token lifetime, in seconds (see [sessions.md](../api/authentication/sessions.md)). |
| `USER_APPROVAL_REQUIRED` | `true` | New registrations start `pending` unless the value is exactly `false` (the exception to the boolean rule, see [Rules](#rules)); then they start `approved` (see [gate.md](../api/authentication/gate.md#user-status-at-the-gate)). Defaults to `true` because open registration lets a stranger store SMTP connections and make the server open TCP connections to arbitrary hosts and ports (test email, `POST /v1/messages`): an SSRF-style probe of the internal network. Openness is opt-in; the development `.env` may set `false`. |
| `TRUSTED_PROXY_HOPS` | `1` | How many `X-Forwarded-For` hops to trust when resolving the client IP (a non-negative count, see [Rules](#rules); resolution in [client-ip.md](../api/conventions/client-ip.md)). Set it to `0` when no reverse proxy sits in front of the app; otherwise clients can spoof their IP through `X-Forwarded-For`. |
| `AUTHORIZATION_REQUEST_TTL` | `3600` (1 h) | Lifetime of a device request, in seconds: `expires_at = now + TTL` (see [requester.md](../api/endpoints/auth/device-authorization/requester.md#post-authauthorization-requestsjson)). |
| `AUTHORIZATION_REQUEST_CREATE_LIMIT` | `5` | Creates allowed per client IP and, separately, per username within the window (a count, see [Rules](#rules)). |
| `AUTHORIZATION_REQUEST_CREATE_WINDOW` | `60` | Rate-limit window of the create endpoint, in seconds. |
| `AUTHORIZATION_REQUEST_MAX_OPEN_PER_USER` | `5` | Open requests per user; the oldest is expired to make room (a count). |
| `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS` | `5` | Wrong passwords before the per-request authorize lockout (a count, see [approver.md](../api/endpoints/auth/device-authorization/approver.md#lockout)). |
| `AUTHORIZATION_REQUEST_AUTHORIZE_LOCK_TTL` | `300` (5 min) | Per-request lockout duration, in seconds: `authorize_locked_until = now + TTL`. |

### Origins

Used by [api/conventions/origin-check.md](../api/conventions/origin-check.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `ALLOWED_ORIGINS` | empty | Comma-separated bare origins (e.g. `https://admin.example.com`) trusted by the origin check and CORS; exact match only. |
| `FRONTEND_BASE_URL` | empty | Fallback trusted origin when `ALLOWED_ORIGINS` is empty; base URL of links in emails. When empty, system emails (password recovery) are not sent, since their link would be broken (see [recovery.md](../api/endpoints/auth/recovery.md#recovery-email)). |

- `ALLOWED_ORIGINS` is split on `,`; each entry is trimmed and empty entries are dropped (see
  [origin-check.md](../api/conventions/origin-check.md#trusted-origins-and-cors)).
- **Bare-origin rule:** each entry must be a bare `scheme://host[:port]` origin with an `http`
  or `https` scheme: no path (not even a trailing `/`), query, fragment or user info.
  Otherwise boot is refused with `ALLOWED_ORIGINS: invalid origin`.
- **`https` rule:** an `http` entry refuses boot with `ALLOWED_ORIGINS: not https`, unless
  `PHP_ENV` is exactly `development` or `test`. This is the same environment rule as the
  [development key](#development-key): unset, empty or any other value counts as production.
- Each failure is reported at most once, however many entries fail, and never with a value.
- When `ALLOWED_ORIGINS` has no entries, only the `scheme://host[:port]` part of
  `FRONTEND_BASE_URL` is trusted.

### Password recovery and account edit

Used by [api/endpoints/auth/recovery.md](../api/endpoints/auth/recovery.md) and
[api/endpoints/auth/account.md](../api/endpoints/auth/account.md).

| Setting | Default | Effect |
| --- | --- | --- |
| `PASSWORD_RESET_TOKEN_TTL` | `1800` (30 min) | Password-reset token lifetime, in seconds: `expires_at = created_at + TTL` (see [recovery.md](../api/endpoints/auth/recovery.md#password-reset-tokens)). |
| `PASSWORD_RESET_COOLDOWN` | `60` | Seconds after a user's last password-reset token during which recover creates no new token and sends no email (see [recovery.md](../api/endpoints/auth/recovery.md#post-authrecoverjson)). |
| `ACCOUNT_EDIT_MAX_ATTEMPTS` | `5` | Failed `PATCH /auth/account.json` attempts before locking (a count, see [Rules](#rules); see [account.md](../api/endpoints/auth/account.md#lockout)). |
| `ACCOUNT_EDIT_LOCK_TTL` | `300` (5 min) | Lock duration, in seconds: `locked_until = now + TTL` (see [account.md](../api/endpoints/auth/account.md#lockout)). |

There is no mail-enabled flag and there are no `SYSTEM_SMTP_*` settings: system emails are
sent through the system organization's active SMTP connection (see
[recovery.md](../api/endpoints/auth/recovery.md#recovery-email)).

## Secret keys

- A key is the **raw environment string**, used as is; its length is measured in **bytes**.
- The app **refuses to boot** when `SECRET_KEY` is missing or shorter than **32 bytes**.
- Generate keys from **at least 32 CSPRNG bytes**, hex- or base64url-encoded (e.g.
  `openssl rand -hex 32`).
- Keys must not contain `,` (the `PREVIOUS_SECRET_KEYS` separator).
- `PREVIOUS_SECRET_KEYS` is split on `,` and each entry has its surrounding whitespace
  trimmed. An **empty entry** (e.g. a trailing or doubled comma) refuses boot, and every entry
  follows the same length rule as `SECRET_KEY`; an invalid entry also refuses boot.
- Keys are never logged or returned by any endpoint.
- Production keys are generated with a CSPRNG and stored only in the production env file on
  the host (see [deployment/server.md](../../deployment/server.md#folders)).

### Development key

- The shipped development key is exactly:
  `pidgey-development-only-insecure-secret-key-0000`.
  It is 48 bytes (so it passes the length rule), contains no `,`, and is recognizable as fake.
- It lives in two places, which must both hold exactly this value:
  - the root `.env` used by docker compose (`SECRET_KEY=<development key>`);
  - the code constant `ConfigValidator::DEVELOPMENT_SECRET_KEY`, used for recognition.
- **Recognition:** an **exact byte comparison** against that value, for `SECRET_KEY` and for
  each trimmed `PREVIOUS_SECRET_KEYS` entry. There is no prefix or marker matching: a
  different key of the same length is not the development key.
- **When it applies:** boot is refused when the development key is found, **unless**
  `PHP_ENV` is exactly `development` or `test`. Every other value counts as production:
  unset, empty, a different case (e.g. `Production`), or anything else. A forgotten
  `PHP_ENV` on a production host therefore fails safe, and a production host with a real key
  is unaffected.
- **Order:** a separate check, run after the missing / empty-entry / length checks.
- **Failure names:** `SECRET_KEY: development key` and
  `PREVIOUS_SECRET_KEYS: development key`. Each is reported at most once (even when several
  `PREVIOUS_SECRET_KEYS` entries match) and never with a value.
- **Refusal:** the same as every other boot-refusing rule on this page: `bin/check-config`
  names the failure and exits `1`; the web boot follows the invalid-configuration path (the
  error is logged and a `500 INTERNAL_ERROR` envelope is answered, see
  [request-handler/front-controller.md](../request-handler/front-controller.md#failures-outside-the-wrapper)).

### SMTP encryption keys

`SMTP_ENCRYPTION_KEY` and `PREVIOUS_SMTP_ENCRYPTION_KEYS` follow the same rules as
`SECRET_KEY` and `PREVIOUS_SECRET_KEYS` (see [Secret keys](#secret-keys)):

- A key is the raw environment string, at least **32 bytes**, with no `,`, generated from at
  least 32 CSPRNG bytes (e.g. `openssl rand -hex 32`).
- The app **refuses to boot** when `SMTP_ENCRYPTION_KEY` is missing or shorter than 32 bytes.
- `PREVIOUS_SMTP_ENCRYPTION_KEYS` is split on `,` and each entry is trimmed; an empty entry
  refuses boot, and every entry follows the same length rule.
- **Same as `SECRET_KEY`:** boot is refused when `SMTP_ENCRYPTION_KEY` or any
  `PREVIOUS_SMTP_ENCRYPTION_KEYS` entry is byte-equal to `SECRET_KEY`.
- **Duplicate key:** boot is refused when two configured SMTP keys (the current key and the
  previous keys) share a key id (see
  [password-encryption.md](../email/password-encryption.md#keys)), e.g. the current key also
  listed in `PREVIOUS_SMTP_ENCRYPTION_KEYS`, or a previous key listed twice.
- **Development key:** exactly `pidgey-development-only-insecure-smtp-key-0000000` (49 bytes,
  no `,`). It lives in the root `.env` (`SMTP_ENCRYPTION_KEY=<development key>`) and in the
  code constant `ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY`, which must both hold
  exactly this value. It is recognized by exact byte comparison (for `SMTP_ENCRYPTION_KEY`
  and each trimmed `PREVIOUS_SMTP_ENCRYPTION_KEYS` entry) and refused unless `PHP_ENV` is
  exactly `development` or `test`, as for the [`SECRET_KEY` development key](#development-key).
- Keys are never logged or returned by any endpoint, and live only in the environment (the
  production env file on the host), never in the database. Losing the key means every stored
  SMTP password must be re-entered, so it is backed up separately from the database.
- **Failure names** (each reported at most once, never with a value):
  - `SMTP_ENCRYPTION_KEY: missing`, `SMTP_ENCRYPTION_KEY: too short`,
    `SMTP_ENCRYPTION_KEY: development key`, `SMTP_ENCRYPTION_KEY: same as SECRET_KEY`;
  - `PREVIOUS_SMTP_ENCRYPTION_KEYS: empty entry`, `PREVIOUS_SMTP_ENCRYPTION_KEYS: too short`,
    `PREVIOUS_SMTP_ENCRYPTION_KEYS: development key`,
    `PREVIOUS_SMTP_ENCRYPTION_KEYS: same as SECRET_KEY`,
    `PREVIOUS_SMTP_ENCRYPTION_KEYS: duplicate key`.

## Env file

An optional env file supplies configuration where the process environment does not, as on the
production host (see [deployment/server.md](../../deployment/server.md#the-configenv-link)).

- **Location:** `config/.env`, relative to the app root: `source/` in the repository, the
  release root on the server. `config/.env` is git-ignored; in production it is a symlink to
  the shared `.env` outside the release.
- **Format:** one `KEY=VALUE` per line.
  - Lines starting with `#` (comments) and blank lines are ignored.
  - The value is everything after the first `=`.
  - Surrounding single or double quotes around the value are stripped (`KEY="a b"` → `a b`).
  - There is no variable expansion: `$OTHER` and `${OTHER}` are kept literally.
  - A line without `=`, or whose key does not match `[A-Za-z_][A-Za-z0-9_]*`, is skipped.
  - A leading `export ` is **not supported**: `export KEY=VALUE` has a key that does not
    match the pattern above, so the line is skipped.
  - `KEY=` (an empty value) sets the variable to an empty string.
- **Precedence:** the loader never overrides a variable that is already set in the
  environment. A variable set to an empty string counts as set. Only keys that are unset are added, so the rest of the app keeps reading
  everything through `Env`.
- **Missing file:** the loader does nothing and reports nothing. Docker compose, CI and tests
  have no `config/.env` and behave exactly as before.
- **Unreadable file:** if `config/.env` exists but cannot be read (e.g. wrong permissions),
  `bin/check-config` reports an
  `env file: unreadable` failure and exits `1`; it never prints the file's content.
- **When it runs:** before anything reads `Env`, at the start of `public/index.php` and of
  every `bin/*` script.
- The loader never prints or logs keys or values.

## `bin/check-config`

A CLI command that checks the configuration without starting the app; the deploy runs it in
the staging folder before migrating and swapping (see
[deployment/pipeline.md](../../deployment/pipeline.md#deploy-script)).

- Loads the env file (as every `bin/*` does), then runs the same validation the app runs at
  boot: every rule on this page that refuses boot, including the `SECRET_KEY` and
  `PREVIOUS_SECRET_KEYS` rules, the [SMTP encryption key](#smtp-encryption-keys) rules, the
  `sodium` extension check (below), the required `DB_*` settings, the `DB_MIGRATE_*` pairing
  rule (see [Runtime and database](#runtime-and-database)), the `ALLOWED_ORIGINS` rules
  (see [Origins](#origins)), the [SMTP transport](#smtp-transport) rules and the
  [`LOG_LEVEL`](#logging) rule.
- Also checks that the running PHP is **8.4.x**, since production is pinned to PHP 8.4 (see
  [deployment/server.md](../../deployment/server.md#host-requirements)). This catches a wrong
  `PHP_BIN` or a host change.
- Checks that the `sodium` PHP extension is loaded (`extension_loaded('sodium')`), needed to
  encrypt and decrypt SMTP passwords (see [dependencies.md](dependencies.md)). Unlike the PHP
  version check, this check also runs at web boot.
- It does not connect to the database.
- **Exit codes:** `0` when every check passes; `1` when one or more checks fail.
- **Output:** names each failed check (e.g. `SECRET_KEY: too short`,
  `SECRET_KEY: development key`, `DB_HOST: missing`, `PHP version: not 8.4.x`,
  `env file: unreadable`), never a value, not even a partial one. A development key in
  `PREVIOUS_SECRET_KEYS` follows the same naming: `PREVIOUS_SECRET_KEYS: development key`
  (see [Development key](#development-key)).
  The [`ALLOWED_ORIGINS` rules](#origins) report `ALLOWED_ORIGINS: invalid origin` and
  `ALLOWED_ORIGINS: not https`.
  The [SMTP encryption key rules](#smtp-encryption-keys) report the failure names listed
  there, and a missing `sodium` extension reports `PHP extension: sodium missing`.
  The [SMTP transport rules](#smtp-transport) report the failure names listed there, and
  an invalid `LOG_LEVEL` reports `LOG_LEVEL: invalid level`.
  The `DB_MIGRATE_*` pairing rule reports:
  - `DB_MIGRATE_PASSWORD: missing` when `DB_MIGRATE_USER` is set and `DB_MIGRATE_PASSWORD` is
    not;
  - `DB_MIGRATE_USER: missing` when `DB_MIGRATE_PASSWORD` is set and `DB_MIGRATE_USER` is not;
  - nothing when neither is set (the fallback applies).
- **Web boot refusal:** the web boot runs the same validation (without the PHP version check,
  but with the `sodium` extension check) before routing. On any failure it refuses through the invalid-configuration path (the
  failure names, never values, are logged and a `500 INTERNAL_ERROR` envelope is answered, see
  [request-handler/front-controller.md](../request-handler/front-controller.md#failures-outside-the-wrapper)).
  Every request, static files included, therefore needs a valid configuration.
  `bin/check-config` still runs before every release, so an invalid configuration is caught
  before it goes live.

## Pending decisions

None. The log-level variable is `LOG_LEVEL` (see [Logging](#logging)).
