# Issue: Specs: email test infrastructure and frontend alignment

## Description

Specify the **email test infrastructure** and the **frontend alignment** for the email
integration (epic #149), so the implementation issues can be tested end to end. This closes
the open items of `docs/agents/specs/implement-email/testing.md` and
`implement-email/frontend.md` (created by #150), now that #151 (encryption), #152 (transport)
and #153 (endpoint hardening) are settled.

### Context

- `email/testing.md` and `testing/backend.md` plan unit tests with a fake transport and
  integration tests through Mailpit (SMTP 1025, asserted via its HTTP API).
- Today there is a single `mailpit` service (`axllent/mailpit`, **no tag**), with no auth and
  no TLS, shared by `app` and `tests`, so tests that clear the mailbox would wipe the
  development mailbox. CircleCI runs the same untagged image as a service container, with no
  env; CircleCI service containers share one network namespace, so several Mailpit
  containers there need distinct ports.
- One Mailpit instance has one SMTP listener: it cannot serve STARTTLS and implicit TLS at
  once, and once auth is configured it applies to every send.
- The spec rule "credentials require `tls` or `ssl`" (#152) means the authenticated paths
  must run over TLS.
- The host rule (ASCII LDH, #152) rejects `_`, so service names used as SMTP hosts must use
  hyphens.
- `SMTP_HOST` / `SMTP_PORT` in `architecture/configuration.md` are read by no code.
  `SMTP_ALLOW_PRIVATE_HOSTS=true` is documented for the development and test env files but
  is set nowhere yet.
- The frontend survey of the #43 screens found field names, envelope keys and status codes
  aligned with the API spec, and no `dangerouslySetInnerHTML`. The gaps are: no `deactivate`
  client method or button; no `429` handling or mention; no client-side
  "credentials require encryption" or credential-redirection rule; long SMTP replies are not
  wrapped; the test-email help text does not say `to` must be an organization user; the
  Jasmine data is hand-written inline; and `api-compatibility.md` marks every SMTP row ✅.

## Problem

The infra, backend and frontend implementation issues of #149 cannot build their tests
without knowing which SMTP servers exist in tests and CI, how tests reach them, which
certificates are trusted, how the fake server works and what coverage is required. The
frontend screens were never run against the real API and miss the rules #152 and #153
added.

## Expected Behavior

Decisions (agreed in discussion).

### Test infrastructure

1. **Mailpit topology: the dev instance stays, tests get three of their own.**
   - `mailpit` (dev) is unchanged: plain, no auth, no TLS. Only `app` and the manual check
     use it; tests never send to it or clear it. A dedicated `mailpit-plain` (rather than
     reusing the dev instance with per-test subject tokens) keeps the simple
     clear → send → assert flow and never disturbs the development mailbox.
   - Three test-only instances, used by `tests` and CI:

     | Service | Mode | Auth | Paths covered |
     | --- | --- | --- | --- |
     | `mailpit-plain` | plain | none | `encryption: none`, delivery assertions |
     | `mailpit-starttls` | `MP_SMTP_REQUIRE_STARTTLS` | required | `tls`, right/wrong credentials, `530` without credentials |
     | `mailpit-ssl` | `MP_SMTP_REQUIRE_TLS` (implicit) | required | `ssl`, right/wrong credentials |

   - The auth user and password come from a test-only fixture (`MP_SMTP_AUTH` inline, so CI
     needs no file).
   - Every Mailpit image (dev and tests, compose and CI) is **pinned to one exact tag**,
     chosen by the infra issue.
   - Each instance gets **distinct ports** everywhere (required in CI, kept identical in
     compose), e.g. SMTP 2025 / 2587 / 2465 and API 9025 / 9587 / 9465.
   - The services are named with hyphens, because `host` must be an LDH name.
2. **Reaching the instances from tests.** There are three test-only keys, each of the form
   `host:smtpPort:apiPort`: `TEST_MAILPIT_PLAIN`, `TEST_MAILPIT_STARTTLS` and
   `TEST_MAILPIT_SSL`.
   - They are read only by `Pidgey\Tests\Support\Mailpit`, never by the app or
     `ConfigValidator`.
   - When one is missing, the integration test **fails**; it is never skipped.
   - The unused `SMTP_HOST` / `SMTP_PORT` rows are removed from
     `architecture/configuration.md` (and from `.env` / CI).
   - Each test that inspects a mailbox clears that instance first, through
     `DELETE /api/v1/messages`.
3. **TLS fixtures** in `source/tests/fixtures/tls/`, committed, test-only:
   - a test CA;
   - one leaf cert/key whose SAN covers `mailpit-starttls`, `mailpit-ssl` and `localhost`;
   - a wrong-name leaf, signed by the test CA, with SAN `wrong-name.invalid`;
   - an untrusted self-signed leaf for `localhost`;
   - a regeneration script, with long validity.

   The test environment sets `SMTP_CA_FILE` to the test CA, and verification is never
   disabled. The wrong-name and untrusted cases (→ `SMTP_TLS_FAILED`) run against the fake
   server (item 4), not Mailpit. Expired certificates are **not** tested, since date checks
   are OpenSSL's behavior.
4. **Fake SMTP server**, confirming the working assumption:
   - **`FakeSmtpStream`**, in memory, implements the stream abstraction. It is scripted as a
     list of expected client lines and server replies (and failures: timeout, closed, TLS
     failure, pending bytes after `STARTTLS`). Unit tests use it for the reply-by-reply
     mapping, with a frozen clock for the deadline.
   - **`FakeSmtpServer`** is a PHP `stream_socket_server` script started as a **child
     process** (`proc_open`) on `127.0.0.1` with an ephemeral port, printed on stdout. It
     reads a JSON scenario and serves one session, with plain, STARTTLS or implicit TLS
     using the fixture certificates. It covers what Mailpit cannot: accept-and-never-answer
     (timeouts), `tls` without `STARTTLS` advertised, no supported `AUTH`, multi-line,
     malformed and `4xx` replies, and wrong-name or untrusted certificates. The test process
     kills it in teardown.
5. **Environment, compose, CI and Makefile.**
   - The test environment (compose `tests` and CircleCI `backend_tests`) sets:
     `SMTP_ALLOW_PRIVATE_HOSTS=true`, `SMTP_CA_FILE=tests/fixtures/tls/ca.pem`,
     `SMTP_ENCRYPTION_KEY` (the development key in compose, a CI value in CI), the three
     `TEST_MAILPIT_*` keys, and short SMTP timeouts for the fake-server timeout tests (set
     per test, not globally).
   - Development `.env` sets `SMTP_ALLOW_PRIVATE_HOSTS=true`, so `make dev` can reach the dev
     `mailpit`.
   - Compose mounts the TLS fixtures read-only into the two TLS instances, and `tests`
     depends on the three test instances. `make dev-up` is unchanged.
   - CircleCI adds the three service containers with the pinned tag, passing the certificate
     and key **inline as PEM** through env, if the pinned Mailpit version supports it.
     Otherwise the infra issue builds a small test image with the fixtures baked in.
   - The docs to update are `docker/compose.md`, `docker/makefile.md` and
     `deployment/pipeline.md`.
6. **Required coverage** (the acceptance list for the backend issues):
   - **Encryption:** the testable rules of `email/password-encryption.md`, which cover
     round-trip, a fresh nonce, tampering, the wrong key, an associated-data mismatch and
     previous-key decryption.
   - **Canary password:** every success and failure path runs with a canary password. It
     never appears in the response body, in any log record (captured through a Monolog
     `TestHandler` injected into the container's logger) or in any exception message, in
     plain or base64 form.
   - **Transport:** reply-by-reply unit tests per step of `send-flow.md`, as already listed
     in `implement-email/testing.md`. The whole `SMTP_*` mapping table of
     `transport.md#failure` is covered end to end through Mailpit or the fake server.
   - **Concurrency:**
     - Concurrent `activate` runs from a child process that holds the organization lock;
       the test proves the second request waits and exactly one connection ends active.
     - The unique index rejects a second active row inserted directly.
     - A `PATCH` racing an `activate` never changes `active`.
   - **Limits and host check:**
     - The test-email limit (window, lease, `Retry-After`) runs with a frozen clock.
     - The host check is unit-tested through an injectable resolver; one integration test
       runs with `SMTP_ALLOW_PRIVATE_HOSTS=false` against `127.0.0.1` and expects `409`.
   - **Endpoints:** the "Testable rules" of `api/endpoints/pidgey-admin/smtp-connections.md`
     are the endpoint acceptance list.

### Frontend alignment

7. **Deactivate:** add `SmtpConnectionsClient.deactivate(id)` and a **Deactivate** action
   on the active row. It asks for confirmation, warning that sending stops until another
   connection is activated, then refreshes the list.
8. **`429`:** the test modal shows `error.message` like the other failures. `Retry-After`
   is ignored in v1. `screens.md` lists `429` among the modal's outcomes.
9. **Client-side rules**, mirroring the server and reusing the server's message texts:
   - credentials only with `tls` or `ssl`, checked on the merged state;
   - on edit, with stored credentials, a change of host, port or encryption requires a new
     password or **Remove credentials**. This error lands on the password field.
10. **SMTP reply display:** `error.smtp.message` and `smtp.message` are rendered as **text
    only** and wrapped (`text-break`). They are never truncated client-side, since the
    server caps them at 512 characters.
11. **Test-email help text:** it says the recipient must be the email of a user of the
    organization.
12. **Jasmine data:** shared JSON fixtures under `frontend/specs/support/fixtures/smtpConnections/`,
    one per response shape: item, list, `VALIDATION_FAILED`, `SMTP_*` with `smtp`, `409`,
    `429` and `404`. They mirror the spec's example responses exactly; the specs stop
    building response objects inline. They are **hand-maintained copies of the spec
    examples**, not captured from the backend (the backend endpoint tests already assert the
    same shapes, and capturing would couple `source/` tests to `frontend/`). Rule: a PR that
    changes an example response in the API spec updates the matching fixture in the same
    PR. The real-backend check is the manual end-to-end check below.
13. **Definition of done** for the frontend issue:
    - the Jasmine specs use the fixtures;
    - `api-compatibility.md` gets a deactivate row, `429` noted, and accurate ✅;
    - a manual end-to-end check in `make dev` against the dev `mailpit`: create, edit,
      activate, deactivate, test email (delivered and seen in the Mailpit UI), the
      `400` / `502` paths (bad host), and delete.
14. **Out of scope** for the frontend: links from `SMTP_PASSWORD_UNREADABLE` or
    `SMTP_INSECURE_CREDENTIALS` to the edit form.

## Solution

### Where to write

- `docs/agents/specs/implement-email/testing.md` and `implement-email/frontend.md`: close
  every open item (decided entries), and update the building blocks (names, collaborators).
- Durable rules:
  - `email/testing.md`: Mailpit instances, the fake server, the TLS fixtures, the canary
    rule;
  - `testing/backend.md`: the support classes, the test-only keys, the child-process
    fixtures;
  - `architecture/configuration.md`: remove `SMTP_HOST` / `SMTP_PORT`, and note the
    test-only `TEST_MAILPIT_*` keys;
  - `docker/compose.md`: the new services, ports, pinned tag and mounts;
  - `frontend/screens.md` (deactivate, `429`, the client rules, the reply display, the help
    text), `frontend/testing.md` (shared fixtures), `frontend/api-compatibility.md`.
- An architect step for `docker/makefile.md` (if needed), `deployment/pipeline.md` (CI
  service containers and env keys) and `deployment/server.md`, whose line saying the env
  files set `SMTP_ALLOW_PRIVATE_HOSTS=true` becomes true once the infra issue lands.

### Out of scope

Code, compose, CI and Makefile changes (the implementation issues); encryption, transport
and endpoint behavior (#151–#153), except where it changes what must be tested.

### Acceptance

- No Pending decision is left in `implement-email/testing.md` or `implement-email/frontend.md`.
- The test setup is specified precisely enough for the infra and backend issues to build it:
  services, ports, keys, fixtures and coverage list.
- The frontend change list is specific enough for the frontend issue.

## Benefits

- Every SMTP path (plain, STARTTLS, implicit TLS, auth, failures) is tested against a real
  server or a scripted one, in CI as well as locally, without disabling TLS verification.
- Tests never touch the development mailbox.
- The frontend matches the hardened API before anyone runs it against a real backend.
