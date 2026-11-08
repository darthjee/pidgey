# Plan: Specs: email test infrastructure and frontend alignment

Issue: [154-specs-email-test-infrastructure-and-frontend-alignment.md](../../issues/154-specs-email-test-infrastructure-and-frontend-alignment.md)

## Overview

A documentation-only issue. It closes every open item of
`docs/agents/specs/implement-email/testing.md` and `implement-email/frontend.md` with the 14
decisions of the issue, and moves the durable rules into the permanent specs. The specs are
owned by the product owner (see [product-owner.md](product-owner.md)). The deployment and CI
docs under `docs/agents/deployment/` are the architect's, in the step below. No code,
compose, CI or Makefile file changes: those belong to the implementation issues of #149.

## Agents involved

- [product-owner](product-owner.md): specs under `docs/agents/specs/`.
- architect (this file): `docs/agents/deployment/`.

## Shared contracts

Every file must use the same names and values:

- **Mailpit instances:**

  | Service | Mode | Auth | SMTP port | API port |
  | --- | --- | --- | --- | --- |
  | `mailpit` (dev, unchanged) | plain | none | 1025 | 8025 |
  | `mailpit-plain` (tests) | plain | none | 2025 | 9025 |
  | `mailpit-starttls` (tests) | `MP_SMTP_REQUIRE_STARTTLS=true` | required | 2587 | 9587 |
  | `mailpit-ssl` (tests) | `MP_SMTP_REQUIRE_TLS=true` (implicit) | required | 2465 | 9465 |

  Every Mailpit image is pinned to one exact tag, which the infra issue chooses. The auth
  user comes from a test-only fixture, passed inline through `MP_SMTP_AUTH`.
- **Test-only keys**, form `host:smtpPort:apiPort`:
  - `TEST_MAILPIT_PLAIN=mailpit-plain:2025:9025`
  - `TEST_MAILPIT_STARTTLS=mailpit-starttls:2587:9587`
  - `TEST_MAILPIT_SSL=mailpit-ssl:2465:9465`

  They are read only by `Pidgey\Tests\Support\Mailpit`. A missing key fails the test.
- **Test environment** (compose `tests` and CircleCI `backend_tests`): the three keys above,
  plus `SMTP_ALLOW_PRIVATE_HOSTS=true`, `SMTP_CA_FILE=tests/fixtures/tls/ca.pem` and
  `SMTP_ENCRYPTION_KEY`. The development `.env` also sets `SMTP_ALLOW_PRIVATE_HOSTS=true`.
- `SMTP_HOST` / `SMTP_PORT` are **removed**, from the specs and from CI.
- **TLS fixtures** in `source/tests/fixtures/tls/`:
  - `ca.pem`;
  - a leaf for `mailpit-starttls`, `mailpit-ssl` and `localhost`;
  - a wrong-name leaf (`wrong-name.invalid`, signed by the test CA);
  - an untrusted self-signed `localhost` leaf;
  - a regeneration script.

## Architect step — deployment and CI docs

Update `docs/agents/deployment/` to match the contracts above:

- `pipeline.md`:
  - The `backend_tests` service containers become `mysql:9` plus the three test Mailpit
    instances, with the pinned tag, their ports and env (TLS certificate and key inline as
    PEM, or a small test image if the pinned version cannot take inline PEM; the infra issue
    decides).
  - The CI env keys table drops `SMTP_HOST` / `SMTP_PORT` and adds the three
    `TEST_MAILPIT_*` keys, `SMTP_ALLOW_PRIVATE_HOSTS=true`, `SMTP_CA_FILE` and
    `SMTP_ENCRYPTION_KEY`.
  - Mark it as the target state that the infra issue implements.
- `server.md`: the line saying the development and test env files set
  `SMTP_ALLOW_PRIVATE_HOSTS=true` stays, with a note that the infra issue of #149 adds it,
  since no env file sets it today.
- `variables.md`: drop `SMTP_HOST` / `SMTP_PORT` if listed. Do not list the `TEST_MAILPIT_*`
  keys as deployment variables: they are test-only.

### Files to Change

- `docs/agents/deployment/pipeline.md` — CI service containers and env keys.
- `docs/agents/deployment/server.md` — note on `SMTP_ALLOW_PRIVATE_HOSTS` in the env files.
- `docs/agents/deployment/variables.md` — remove `SMTP_HOST` / `SMTP_PORT`, if present.

## Notes

- **Ports:** the port numbers are part of the contract, so compose, CI and the specs agree;
  the infra issue may change them only by updating every file.
- **Inline PEM:** whether Mailpit's `MP_SMTP_TLS_CERT` / `MP_SMTP_TLS_KEY` accept inline PEM
  depends on the pinned version. The specs record both routes; the infra issue picks one.
- **Service names** use hyphens because `host` must be an ASCII LDH name. Docker compose
  and CircleCI accept hyphenated service and container names.
- **Fixture rule:** a PR that changes an example response in the API spec updates the
  matching frontend fixture in the same PR.
