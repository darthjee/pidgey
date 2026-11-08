# Implement Email: Test Infrastructure

Purpose: map the test infrastructure of epic #149 (fake SMTP server, Mailpit, fixtures,
required coverage) to its implementation work and list the open decisions owned by #154.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

Index: [implement-email.md](../implement-email.md).

## Open items

- [ ] **Pending decision:** Mailpit with authentication.
  [email/testing.md](../email/testing.md) tests wrong credentials "where Mailpit is configured
  to require auth", but the `mailpit` service of [docker/compose.md](../docker/compose.md) and
  of CircleCI requires none. Whether one Mailpit instance requires auth (`MP_SMTP_AUTH_FILE`,
  which then also affects development), or a second instance (e.g. `mailpit_auth`) is added
  for the auth paths.
- [ ] **Pending decision:** TLS paths (`tls` and `ssl`) in integration tests: a test CA and a
  certificate for the Mailpit hostname kept in `source/tests/fixtures/`, Mailpit configured
  with them, and the test CA injected through `SMTP_CA_FILE` (decided by #152, see
  [architecture/configuration.md](../architecture/configuration.md#smtp-transport)).
  Certificate verification is **never disabled** in tests. Whether expired / wrong-name certificates are also tested.
- [ ] **Pending decision:** fake SMTP server. *Working assumption, to be confirmed in #154:* an
  in-memory stream (plugged into the stream abstraction of
  [transport.md](transport.md#stream)) scripted with server replies for unit tests, and a
  `stream_socket_server` fixture in the integration suite for what Mailpit cannot produce:
  timeouts (a server that accepts and never answers), `tls` without `STARTTLS` advertised, no
  supported `AUTH`, multi-line and malformed replies, `4xx` replies.
- [ ] **Pending decision:** how the Mailpit HTTP API is reached by the tests (a new
  configuration key such as `MAILPIT_API_URL`, or derived from `SMTP_HOST`), and how
  "Mailpit's mailbox is cleared before each test" coexists with the development mailbox,
  since `tests` and `app` share the same `mailpit` service.
- [ ] **Pending decision:** CI and local changes: CircleCI service containers (a second
  Mailpit, TLS certificates, new environment keys such as the encryption key), and matching
  `docker-compose.yml`, `.env` and Makefile changes
  ([docker/compose.md](../docker/compose.md), [docker/makefile.md](../docker/makefile.md)).
- [ ] **Pending decision:** required coverage beyond the per-endpoint
  [Testable rules](../api/endpoints/pidgey-admin/smtp-connections.md#testable-rules):
  - encryption: round-trip, a fresh nonce per write, tampered bytes, wrong key,
    associated-data mismatch (bytes moved to another row), previous-key decryption if
    adopted ([encryption.md](encryption.md));
  - log capture: no password, `AUTH` payload or key in any captured log line or exception
    message, on success and failure paths. Required by #152: every failure path and the
    success path run with a **canary password**; neither the password nor its base64 forms
    (alone or inside the `AUTH` payload) may appear in the response body, any log record
    (message, context, exception trace) or any exception message
    ([email/send-flow.md](../email/send-flow.md#secret-hygiene));
  - reply-by-reply transport unit tests against the fake stream (required by #152): every
    step's success and failure mapping of
    [email/send-flow.md](../email/send-flow.md#smtp-session) — greeting codes, `EHLO`
    without `HELO` fallback, `STARTTLS` missing / refused / injected bytes, `AUTH` `PLAIN` /
    `LOGIN` / no supported mechanism / `530`, envelope and `DATA` rejections, dot-stuffing,
    multi-line and malformed replies (`SMTP_PROTOCOL_ERROR`), timeouts and the session
    deadline with a frozen clock, best-effort `QUIT`;
  - concurrent `activate` (two connections, two processes) proving exactly one ends active;
  - the `SMTP_*` mapping table of [transport.md](transport.md#failure) end to end.

## Building block: test infrastructure

- **Responsibility:** provides what the unit and integration suites need to test encryption,
  the transport, the send flow and the endpoints: a scriptable fake SMTP server, Mailpit
  helpers (send, read, clear), TLS fixtures, fixtures for SMTP connections, and log capture.
  It ships no production code.
- **Proposed names:** `Pidgey\Tests\Support\FakeSmtpStream` (in-memory),
  `Pidgey\Tests\Support\FakeSmtpServer` (socket fixture), `Pidgey\Tests\Support\Mailpit` (HTTP
  API client), `Pidgey\Tests\Support\CapturedLog`; TLS material under
  `source/tests/fixtures/tls/`. Proposals only.
- **Collaborators:** the stream abstraction and transport ([transport.md](transport.md)),
  `Pidgey\Tests\Support\Fixtures` (`smtpConnection()`),
  `Pidgey\Tests\Support\IntegrationTestCase`, `Pidgey\Tests\Support\DatabaseCleaner`,
  `Pidgey\Tests\Support\SessionEndpointTestCase` and `Pidgey\Tests\Support\RouteGuards`
  (endpoint tests), the `mailpit` and `tests` services.
- **Governing specs:** [email/testing.md](../email/testing.md);
  [testing/backend.md](../testing/backend.md);
  [testing/definition-of-done.md](../testing/definition-of-done.md);
  [docker/compose.md](../docker/compose.md);
  [architecture/configuration.md](../architecture/configuration.md#smtp-development-and-tests).
- **Open items:** every item above.
