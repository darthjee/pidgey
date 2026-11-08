# Email Testing

Purpose: define how email sending is tested: the SMTP servers used by the tests, how tests
reach them, the TLS fixtures and the failure paths.

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
- The auth user and password come from a **test-only fixture**, passed inline through
  `MP_SMTP_AUTH`, so CI needs no file.
- Every Mailpit image (dev and tests, compose and CI) is **pinned to one exact tag**, chosen
  by the infra issue.
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

- `ca.pem`: the test CA;
- a leaf certificate and key whose SAN covers `mailpit-starttls`, `mailpit-ssl` and
  `localhost`, signed by the test CA;
- a wrong-name leaf, signed by the test CA, with SAN `wrong-name.invalid`;
- an untrusted self-signed leaf for `localhost`;
- a regeneration script.

Rules:

- The certificates have a long validity.
- The private keys are test-only and never used outside tests.
- The test environment sets `SMTP_CA_FILE=tests/fixtures/tls/ca.pem`. Certificate
  verification is **never disabled**.
- The wrong-name and untrusted cases (`SMTP_TLS_FAILED`) run against the fake SMTP server,
  not Mailpit.
- Expired certificates are **not** tested: date checks are OpenSSL's behavior.

## Failure paths

- Authentication failures (wrong credentials, `530` without credentials) run against
  `mailpit-starttls` and `mailpit-ssl`.
- What Mailpit cannot produce (timeouts, missing `STARTTLS`, unsupported `AUTH`, multi-line,
  malformed and `4xx` replies, wrong-name or untrusted certificates) runs against the fake
  SMTP server.

See [testing](../testing.md).
