# Email Testing

Purpose: define how email sending is tested: the SMTP servers used by the tests, how tests
reach them, the TLS fixtures, the fake SMTP server and the required coverage.

- Unit tests cover MIME building and the SMTP reply → error code mapping with a fake
  transport.
- Integration tests send real email through the test-only Mailpit instances below and
  assert on delivered messages through Mailpit's HTTP API (recipient, subject, bodies).

## Mailpit instances

The development instance and the test instances are separate, so tests never disturb the
development mailbox.

| Service | Mode | Auth | SMTP port | API port | Used by |
| --- | --- | --- | --- | --- | --- |
| `mailpit` (dev, unchanged) | plain | none | 1025 | 8025 | `app` and the manual check |
| `mailpit-plain` (tests) | plain | none | 2025 | 9025 | `tests`, CI |
| `mailpit-starttls` (tests) | `MP_SMTP_REQUIRE_STARTTLS=true` | required | 2587 | 9587 | `tests`, CI |
| `mailpit-ssl` (tests) | `MP_SMTP_REQUIRE_TLS=true` (implicit TLS) | required | 2465 | 9465 | `tests`, CI |

- The dev `mailpit` is unchanged: tests never send to it and never clear it.
- Paths covered by each test instance:
  - `mailpit-plain`: `encryption: none` and the delivery assertions.
  - `mailpit-starttls`: `tls` with right and wrong credentials, and `530` without
    credentials.
  - `mailpit-ssl`: `ssl` with right and wrong credentials.
- **Why three:** one Mailpit instance has a single SMTP listener, so it cannot serve
  STARTTLS and implicit TLS at once; once auth is configured it applies to every send; and
  credentials require `tls` or `ssl` ([smtp-connections.md](smtp-connections.md)), so the
  authenticated paths must run over TLS.
- The auth user and password are the constants `Mailpit::AUTH_USER` (`pidgey-test`) and
  `Mailpit::AUTH_PASSWORD` (`pidgey-test-password`) of `Pidgey\Tests\Support\Mailpit`. They
  are repeated literally, inline, in `MP_SMTP_AUTH` (`pidgey-test:pidgey-test-password`) in
  compose and CI, so no auth file is needed.
- Every Mailpit (dev and tests, compose and CI) is **pinned to one exact tag**:
  `axllent/mailpit:v1.32.0`. In CI the Mailpit binary of that image is copied into the CI
  base image (see [Reaching the instances](#reaching-the-instances)).
- Each instance has **distinct ports** everywhere: required in CI, where service containers
  share one network namespace, and kept identical in compose. The ports are part of the
  contract; changing one means updating compose, CI and the specs together.
- Service names use **hyphens**, because an SMTP `host` must be an ASCII LDH name.

See [docker/compose.md](../docker/compose.md) for the services.

## Reaching the instances

Tests reach each test instance through a **test-only key** of the form
`host:smtpPort:apiPort`:

| Key | Value |
| --- | --- |
| `TEST_MAILPIT_PLAIN` | `mailpit-plain:2025:9025` |
| `TEST_MAILPIT_STARTTLS` | `mailpit-starttls:2587:9587` |
| `TEST_MAILPIT_SSL` | `mailpit-ssl:2465:9465` |

- The table shows the compose values. CI uses **`localhost`** with the same ports
  (`localhost:2025:9025`, `localhost:2587:9587`, `localhost:2465:9465`): CircleCI cannot
  mount files into service containers and Mailpit reads TLS files only by path, so
  `backend_tests` starts the three instances in the primary container, from the Mailpit
  binary of the CI base image, with the fixtures of the checkout.
- They are read only by `Pidgey\Tests\Support\Mailpit`, never by the application or
  `ConfigValidator`.
- When a key is missing, the integration test **fails**; it is never skipped.

## Mailbox

- Each test that inspects a mailbox **clears that instance first**, through
  `DELETE /api/v1/messages`.
- It reads the delivered messages through `GET /api/v1/messages` and
  `GET /api/v1/message/{id}`.

## TLS fixtures

Committed, test-only material in `source/tests/fixtures/tls/`:

| File | Content |
| --- | --- |
| `ca.pem` | The test CA certificate |
| `server.pem` / `server-key.pem` | Leaf signed by the test CA; SAN `mailpit-starttls`, `mailpit-ssl`, `localhost` |
| `wrong-name.pem` / `wrong-name-key.pem` | Leaf signed by the test CA; SAN `wrong-name.invalid` |
| `untrusted.pem` / `untrusted-key.pem` | Self-signed leaf; SAN `localhost` |
| `generate.sh` | Regenerates all of the above with `openssl` |

- The CA private key is **not committed**: `generate.sh` creates a new CA on each run and
  re-signs every leaf with it, so all files are regenerated together.
- The two Mailpit TLS instances use `server.pem` / `server-key.pem`.

Rules:

- The certificates have a long validity (100 years).
- The private keys are test-only and never used outside tests.
- The test environment sets `SMTP_CA_FILE=tests/fixtures/tls/ca.pem`. Certificate
  verification is **never disabled**.
- The wrong-name and untrusted cases (`SMTP_TLS_FAILED`) run against the fake SMTP server,
  not Mailpit.
- Expired certificates are **not** tested: date checks are OpenSSL's behavior.

## Fake SMTP server

Two fakes cover what Mailpit cannot produce. Both are test-only support classes (see
[testing/backend.md](../testing/backend.md#email-test-support)).

### `Pidgey\Tests\Support\FakeSmtpStream`

- In memory; implements the transport's stream abstraction (open, local address, write a
  line, read a line, upgrade to TLS, close).
- Scripted as an ordered list of expected client lines and server replies. A client line
  that does not match the script fails the test.
- Can inject the stream-level failures: timeout, connection closed by the peer, TLS
  failure, and bytes pending after `STARTTLS`.
- Used by the transport unit tests for the reply-by-reply mapping, with a **frozen clock**
  for the timeouts and the session deadline.

### `Pidgey\Tests\Support\FakeSmtpServer`

- A PHP `stream_socket_server` script started as a **child process** (`proc_open`). It
  listens on `127.0.0.1` with an ephemeral port, which it prints on stdout.
- Reads a JSON scenario and serves **one session**: plain, STARTTLS or implicit TLS, with the
  [TLS fixtures](#tls-fixtures).
- Covers, in integration tests:
  - a server that accepts and never answers (timeouts);
  - `tls` without `STARTTLS` advertised;
  - no supported `AUTH` mechanism;
  - multi-line, malformed and `4xx` replies;
  - wrong-name and untrusted certificates (`SMTP_TLS_FAILED`).
- The test process kills it in teardown.
- Timeout tests set short SMTP timeouts **per test**, never globally.

## Failure paths

- Unreachable hosts and ports: `SMTP_CONNECTION_FAILED`.
- Authentication failures (wrong credentials, `530` without credentials) run against
  `mailpit-starttls` and `mailpit-ssl`.
- What Mailpit cannot produce runs against the fake SMTP server (above).

## Required coverage

The acceptance list for the backend issues of the email integration. Each item must be
covered by automated tests.

- **Encryption:** the [Testable rules](password-encryption.md#testable-rules) of the password
  encryption (round-trip, a fresh nonce, tampering, the wrong key, an associated-data
  mismatch, previous-key decryption).
- **Canary password:** every success and failure path runs with a canary password. The
  password and its base64 forms (alone and inside the `AUTH` payload) never appear:
  - in the response body;
  - in any log record (message, context, exception trace), captured through
    `Pidgey\Tests\Support\CapturedLog`;
  - in any exception message.

  See [send-flow.md](send-flow.md#secret-hygiene).
- **Transport:**
  - reply-by-reply unit tests, against `FakeSmtpStream`, for the success and failure mapping
    of every step of the [SMTP session](send-flow.md#smtp-session): greeting codes, `EHLO`
    without `HELO` fallback, `STARTTLS` missing / refused / with injected bytes, `AUTH`
    `PLAIN` / `LOGIN` / no supported mechanism / `530`, envelope and `DATA` rejections,
    dot-stuffing, multi-line and malformed replies, timeouts and the session deadline with
    a frozen clock, best-effort `QUIT`;
  - every `SMTP_*` code of [failures.md](failures.md#codes), with its HTTP status, message
    and `error.smtp`, end to end through Mailpit or `FakeSmtpServer`.
- **Concurrency:**
  - concurrent `activate`: a child process holds the organization lock; the test proves the
    second request waits and exactly one connection ends active;
  - the unique index rejects a second active row inserted directly;
  - a `PATCH` racing an `activate` never changes `active`.
- **Limits and host check:**
  - the test-email limit (window, lease, `Retry-After`) runs with a frozen clock;
  - the [host check](send-flow.md#host-check) is unit-tested through an injectable resolver;
    one integration test runs with `SMTP_ALLOW_PRIVATE_HOSTS=false` against `127.0.0.1` and
    expects `409 SMTP_HOST_NOT_ALLOWED`.
- **Endpoints:** the
  [Testable rules](../api/endpoints/pidgey-admin/smtp-connections.md#testable-rules) of the
  SMTP connection endpoints.

See [testing](../testing.md).
