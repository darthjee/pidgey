# Issue: Email test infrastructure: Mailpit instances, TLS fixtures and CI

## Description

Part of epic #149 (email integration). Builds the SMTP test infrastructure settled by #154:
the test Mailpit instances, the TLS fixtures, compose, CI and the `Mailpit` test support
class.

### Specs

- `docs/agents/specs/email/testing.md` (Mailpit instances, reaching them, TLS fixtures);
- `docs/agents/specs/docker/compose.md#test-mailpit-instances` and `#environment`;
- `docs/agents/specs/testing/backend.md#email-test-support`;
- `docs/agents/specs/architecture/configuration.md#test-only-keys` (`TEST_MAILPIT_*`);
- `docs/agents/deployment/pipeline.md`;
- implementation notes: `docs/agents/specs/implement-email/testing.md`.

## Problem

The transport, send-flow and endpoint issues of #149 need real SMTP servers (plain,
STARTTLS, implicit TLS, with auth), trusted test certificates and a way to read delivered
mail, in compose and in CI. Today there is one unpinned, plain dev `mailpit`, and CI still
sets the obsolete `SMTP_HOST` / `SMTP_PORT`.

The specs assumed CI could pass the TLS certificate inline as PEM. Mailpit cannot:
`MP_SMTP_TLS_CERT` / `MP_SMTP_TLS_KEY` accept only a file path (its `config.go` checks that
the file exists). The `sans:` prefix generates a self-signed certificate that the test CA
does not sign. CircleCI service containers cannot mount files, so CI needs another way.

## Expected Behavior

### In scope

1. **Pinned tag:** `axllent/mailpit:v1.32.0` everywhere: the dev `mailpit`, the three
   compose test instances and the CI binary.
2. **TLS fixtures** in `source/tests/fixtures/tls/`, committed and test-only:
   - `ca.pem`, the test CA;
   - a leaf certificate and key, signed by the CA, whose SAN covers `mailpit-starttls`,
     `mailpit-ssl` and `localhost`;
   - a wrong-name leaf, signed by the CA, with SAN `wrong-name.invalid`;
   - an untrusted self-signed leaf for `localhost`;
   - a regeneration script;
   - long validity on every certificate.
3. **Test auth credentials** (decided): constants in `Pidgey\Tests\Support\Mailpit`
   (`pidgey-test` / `pidgey-test-password`). They are repeated literally in `MP_SMTP_AUTH` in
   compose and CI. There is no extra env key; `TEST_MAILPIT_*` stay the only test-only keys.
4. **Compose:**
   - `mailpit-plain`, `mailpit-starttls` (`MP_SMTP_REQUIRE_STARTTLS=true`) and
     `mailpit-ssl` (`MP_SMTP_REQUIRE_TLS=true`);
   - the ports go in `MP_SMTP_BIND_ADDR` / `MP_UI_BIND_ADDR` (2025/9025, 2587/9587,
     2465/9465) and are not published to the host;
   - `MP_SMTP_AUTH` on the two TLS instances;
   - `source/tests/fixtures/tls/` mounted read-only into the two TLS instances, with
     `MP_SMTP_TLS_CERT` / `MP_SMTP_TLS_KEY`;
   - `tests` depends on the three instances instead of `mailpit`, and gets
     `TEST_MAILPIT_*` (compose service names), `SMTP_ALLOW_PRIVATE_HOSTS=true` and
     `SMTP_CA_FILE=tests/fixtures/tls/ca.pem`;
   - `make dev-up` and the dev `mailpit` are unchanged except for the tag.
5. **CI (decided: Mailpit binary in the CI base image):**
   - the Mailpit `v1.32.0` binary is added to `darthjee/circleci_pidgey-base`, bumped to
     `0.0.4` through the existing `build-ci-base` / `push-ci-base` targets;
   - `backend_tests` drops the `axllent/mailpit` service container;
   - a step starts the three instances in the background from the binary, with the same
     modes, ports and auth as compose and the fixture files from the checkout, and waits
     until each API answers;
   - the env drops `SMTP_HOST` / `SMTP_PORT` and adds `TEST_MAILPIT_PLAIN=localhost:2025:9025`,
     `TEST_MAILPIT_STARTTLS=localhost:2587:9587`, `TEST_MAILPIT_SSL=localhost:2465:9465`,
     `SMTP_ALLOW_PRIVATE_HOSTS=true` and `SMTP_CA_FILE=tests/fixtures/tls/ca.pem`.
     `SMTP_ENCRYPTION_KEY` is already set (#160).
6. **Development `.env`:** add `SMTP_ALLOW_PRIVATE_HOSTS=true`; drop `SMTP_HOST` /
   `SMTP_PORT`.
7. **`Pidgey\Tests\Support\Mailpit`:**
   - parses a `TEST_MAILPIT_*` key (`host:smtpPort:apiPort`) and fails the test when the key
     is missing or malformed (never skips);
   - exposes host, SMTP port and the auth constants;
   - clears (`DELETE /api/v1/messages`), lists (`GET /api/v1/messages`) and reads
     (`GET /api/v1/message/{id}`) through the HTTP API;
   - uses no new Composer dependency.
8. **Smoke test** (permanent, `tests/integration/Support/MailpitTest.php`). It uses a minimal
   raw-socket SMTP exchange that is private to the test, since the transport is #163:
   - `mailpit-plain`: send one message, then clear, list and read it back through `Mailpit`;
   - `mailpit-starttls`: `STARTTLS` verified against `ca.pem`, `AUTH PLAIN` with the right
     password returns `235`, and a wrong one returns `535`;
   - `mailpit-ssl`: the same over implicit TLS;
   - certificate verification is never disabled.
9. **Docs:**
   - replace the "inline PEM, or a test image" wording with the CI-binary decision in
     `email/testing.md`, `docker/compose.md`, `deployment/pipeline.md` and
     `implement-email/testing.md`;
   - record the pinned tag and the auth constants;
   - note that in CI the keys use `localhost`.

### Out of scope

- `FakeSmtpStream` / `FakeSmtpServer` (SMTP transport, #163).
- `CapturedLog` (logger and clock, #162).
- The lock-holder script (SMTP connection endpoints, #164).
- Any production code, and any validation of `SMTP_ALLOW_PRIVATE_HOSTS` / `SMTP_CA_FILE` by
  `ConfigValidator` (those keys are set here, read later).

### Edge cases

- A missing or malformed `TEST_MAILPIT_*` key fails the test with a message that names the
  key.
- The CI start step fails the job when an instance does not answer in time, rather than
  letting the tests fail later with connection errors.
- The leaf SAN must cover `localhost`, since CI reaches the instances there.

## Solution

### Acceptance

- `make tests` and CircleCI run the smoke test green, with TLS verification enabled.
- The dev `mailpit` and `make dev-up` are unchanged except for the pinned tag.
- No unpinned or `latest` Mailpit image or binary is left.
- The CI base image `0.0.4` is pushed before the PR that uses it is merged (the PR notes
  this release step).
- The matching items of `implement-email/testing.md` (TLS fixtures, compose, CircleCI,
  `.env`, the `Mailpit` support class) are ticked in the same PR.

### Dependencies

None. Agents: infra (fixtures script, compose, CI image, CI config, `.env`, docs), backend
(`Mailpit` support class and smoke test).

## Benefits

- Every SMTP mode the app supports can be exercised against a real server, locally and in
  CI, with real certificate verification.
- Tests never touch the development mailbox.
- The transport and endpoint issues start from a working, proven test setup.
