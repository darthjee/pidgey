# Implement Email: Test Infrastructure

Purpose: map the test infrastructure of epic #149 (fake SMTP server, Mailpit, fixtures,
required coverage) to its implementation work; the decisions owned by #154 are settled in
the permanent specs linked below.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

Index: [implement-email.md](../implement-email.md).

## Open items

All settled by #154.

- [x] **Mailpit with authentication:** the development `mailpit` is unchanged (plain, no
  auth) and tests never use it; three test-only instances: `mailpit-plain` (plain, no auth),
  `mailpit-starttls` (`MP_SMTP_REQUIRE_STARTTLS`, auth) and `mailpit-ssl`
  (`MP_SMTP_REQUIRE_TLS`, auth), with the auth user inline through `MP_SMTP_AUTH`, one pinned
  image tag, distinct ports and hyphenated names —
  [email/testing.md](../email/testing.md#mailpit-instances),
  [docker/compose.md](../docker/compose.md#test-mailpit-instances).
- [x] **TLS paths:** committed test-only fixtures in `source/tests/fixtures/tls/` (test CA, a
  leaf for `mailpit-starttls` / `mailpit-ssl` / `localhost`, a wrong-name leaf, an untrusted
  self-signed leaf, a regeneration script); `SMTP_CA_FILE` points to the test CA and
  verification is never disabled; wrong-name and untrusted certificates are tested against
  the fake server; expired certificates are not tested —
  [email/testing.md](../email/testing.md#tls-fixtures).
- [x] **Fake SMTP server:** the working assumption is confirmed: `FakeSmtpStream` (in
  memory, scripted, with injected stream failures and a frozen clock) for unit tests, and
  `FakeSmtpServer` (a `stream_socket_server` child process on `127.0.0.1`, one session per
  JSON scenario, plain / STARTTLS / implicit TLS) for integration tests —
  [email/testing.md](../email/testing.md#fake-smtp-server).
- [x] **Reaching Mailpit and the mailbox:** three test-only keys `TEST_MAILPIT_PLAIN`,
  `TEST_MAILPIT_STARTTLS` and `TEST_MAILPIT_SSL` (`host:smtpPort:apiPort`), read only by
  `Pidgey\Tests\Support\Mailpit`; a missing key fails the test; each test clears the
  instance it inspects first (`DELETE /api/v1/messages`); `SMTP_HOST` / `SMTP_PORT` are
  removed — [email/testing.md](../email/testing.md#reaching-the-instances),
  [architecture/configuration.md](../architecture/configuration.md#test-only-keys).
- [x] **CI and local changes:** the test environment (compose `tests`, CircleCI
  `backend_tests`) sets the three keys, `SMTP_ALLOW_PRIVATE_HOSTS=true`,
  `SMTP_CA_FILE=tests/fixtures/tls/ca.pem` and `SMTP_ENCRYPTION_KEY`; the development `.env`
  sets `SMTP_ALLOW_PRIVATE_HOSTS=true`; `tests` depends on the three test instances;
  CircleCI runs them as service containers with the certificate inline as PEM, or a small
  test image if the pinned version cannot take it; `make dev-up` is unchanged —
  [docker/compose.md](../docker/compose.md#environment),
  [docker/makefile.md](../docker/makefile.md),
  [deployment/pipeline.md](../../deployment/pipeline.md).
- [x] **Required coverage:** encryption testable rules, the canary password (response body,
  captured logs, exception messages, plain and base64), reply-by-reply transport tests and
  every `SMTP_*` code end to end, concurrency (`activate` against a lock-holding child
  process, the unique index, `PATCH` racing `activate`), the test-email limit with a frozen
  clock, the host check (injectable resolver, one `SMTP_ALLOW_PRIVATE_HOSTS=false`
  integration test), and the endpoint Testable rules —
  [email/testing.md](../email/testing.md#required-coverage).

## Building block: test infrastructure

- **Responsibility:** provides what the unit and integration suites need to test encryption,
  the transport, the send flow and the endpoints: a scriptable fake SMTP stream and server,
  Mailpit helpers (clear, list, read), TLS fixtures, fixtures for SMTP connections, and log
  capture. It ships no production code.
- **Proposed names:** `Pidgey\Tests\Support\FakeSmtpStream` (in memory),
  `Pidgey\Tests\Support\FakeSmtpServer` (child-process socket fixture),
  `Pidgey\Tests\Support\Mailpit` (HTTP API client, the only reader of `TEST_MAILPIT_*`),
  `Pidgey\Tests\Support\CapturedLog` (Monolog `TestHandler`); the child-process scripts
  (fake SMTP server, organization lock holder) under `source/tests/fixtures/`; TLS material
  under `source/tests/fixtures/tls/`
  ([testing/backend.md](../testing/backend.md#email-test-support)).
- **Collaborators:** the stream abstraction and transport ([transport.md](transport.md)),
  `Pidgey\Tests\Support\Fixtures` (`smtpConnection()`),
  `Pidgey\Tests\Support\IntegrationTestCase`, `Pidgey\Tests\Support\DatabaseCleaner`,
  `Pidgey\Tests\Support\SessionEndpointTestCase` and `Pidgey\Tests\Support\RouteGuards`
  (endpoint tests), the `mailpit-plain`, `mailpit-starttls`, `mailpit-ssl` and `tests`
  services.
- **Governing specs:** [email/testing.md](../email/testing.md);
  [testing/backend.md](../testing/backend.md);
  [testing/definition-of-done.md](../testing/definition-of-done.md);
  [docker/compose.md](../docker/compose.md); [docker/makefile.md](../docker/makefile.md);
  [architecture/configuration.md](../architecture/configuration.md#test-only-keys);
  [deployment/pipeline.md](../../deployment/pipeline.md).
- **Open items:** none.

## Implementation work

Names are **proposals** (see the lifecycle rules in [implement-email.md](../implement-email.md)).

1. **TLS fixtures** — `source/tests/fixtures/tls/`: the test CA, the three leaves and the
   regeneration script ([TLS fixtures](../email/testing.md#tls-fixtures)).
2. **Compose** — the pinned tag on `mailpit`; the services `mailpit-plain`,
   `mailpit-starttls` and `mailpit-ssl` (ports, `MP_SMTP_AUTH`, the read-only TLS mount);
   `tests` depends on them and gets the test environment
   ([docker/compose.md](../docker/compose.md#test-mailpit-instances)).
3. **CircleCI** — the three service containers with the pinned tag and their env, and the
   `backend_tests` env keys ([deployment/pipeline.md](../../deployment/pipeline.md)).
4. **Development `.env`** — sets `SMTP_ALLOW_PRIVATE_HOSTS=true`; drops `SMTP_HOST` /
   `SMTP_PORT` ([docker/compose.md](../docker/compose.md#environment)).
5. **Support classes** — `Mailpit`, `FakeSmtpStream`, `FakeSmtpServer` and its server
   script, `CapturedLog`, the lock-holder script
   ([testing/backend.md](../testing/backend.md#email-test-support)).
6. **Coverage** — the tests of the [required coverage](../email/testing.md#required-coverage)
   list, delivered by the backend issues that implement each part (encryption, transport,
   endpoints, test email).
