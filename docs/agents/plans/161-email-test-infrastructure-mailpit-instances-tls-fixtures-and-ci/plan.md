# Plan: Email test infrastructure: Mailpit instances, TLS fixtures and CI

Issue: [161-email-test-infrastructure-mailpit-instances-tls-fixtures-and-ci.md](../../issues/161-email-test-infrastructure-mailpit-instances-tls-fixtures-and-ci.md)

## Overview

This issue adds the three test Mailpit instances (plain, STARTTLS, implicit TLS with auth),
the committed TLS fixtures, compose and CircleCI wiring, the `Pidgey\Tests\Support\Mailpit`
helper and a permanent smoke test that reaches each instance.

- Compose runs the instances as services with the fixtures mounted.
- CI cannot mount files into service containers, and Mailpit reads certificates only from
  file paths. So the Mailpit binary is baked into the CI base image (`0.0.4`), and
  `backend_tests` starts the three instances on `localhost` from the checkout.

## Agents involved

- [infra](infra.md)
- [backend](backend.md)
- [product-owner](product-owner.md)

## Shared contracts

### Mailpit version

`axllent/mailpit:v1.32.0` is used everywhere:

- the dev `mailpit` service;
- the three compose test services;
- the CI binary, copied from that image (`COPY --from=axllent/mailpit:v1.32.0 /mailpit
  /usr/local/bin/mailpit`).

### Instances

| Instance | Mode env | SMTP bind | API bind | Auth |
| --- | --- | --- | --- | --- |
| `mailpit-plain` | none | `0.0.0.0:2025` | `0.0.0.0:9025` | none |
| `mailpit-starttls` | `MP_SMTP_REQUIRE_STARTTLS=true` | `0.0.0.0:2587` | `0.0.0.0:9587` | `MP_SMTP_AUTH=pidgey-test:pidgey-test-password` |
| `mailpit-ssl` | `MP_SMTP_REQUIRE_TLS=true` | `0.0.0.0:2465` | `0.0.0.0:9465` | `MP_SMTP_AUTH=pidgey-test:pidgey-test-password` |

- The binds are set through `MP_SMTP_BIND_ADDR` / `MP_UI_BIND_ADDR`.
- The two TLS instances set `MP_SMTP_TLS_CERT` / `MP_SMTP_TLS_KEY` to the server leaf
  below. In compose the fixtures are mounted at `/fixtures/tls` (read-only). In CI the paths
  are the checkout's `tests/fixtures/tls/...`.

### TLS fixture files (`source/tests/fixtures/tls/`)

| File | Content |
| --- | --- |
| `ca.pem` | Test CA certificate (the CA key is not committed) |
| `server.pem` / `server-key.pem` | Leaf signed by the CA; SAN `DNS:mailpit-starttls`, `DNS:mailpit-ssl`, `DNS:localhost` |
| `wrong-name.pem` / `wrong-name-key.pem` | Leaf signed by the CA; SAN `DNS:wrong-name.invalid` |
| `untrusted.pem` / `untrusted-key.pem` | Self-signed leaf; SAN `DNS:localhost` |
| `generate.sh` | Regenerates all of the above with `openssl` (a new CA each run), 100-year validity |

### Test environment keys

| Key | Compose `tests` | CircleCI `backend_tests` |
| --- | --- | --- |
| `TEST_MAILPIT_PLAIN` | `mailpit-plain:2025:9025` | `localhost:2025:9025` |
| `TEST_MAILPIT_STARTTLS` | `mailpit-starttls:2587:9587` | `localhost:2587:9587` |
| `TEST_MAILPIT_SSL` | `mailpit-ssl:2465:9465` | `localhost:2465:9465` |
| `SMTP_ALLOW_PRIVATE_HOSTS` | `true` | `true` |
| `SMTP_CA_FILE` | `tests/fixtures/tls/ca.pem` | `tests/fixtures/tls/ca.pem` |
| `SMTP_ENCRYPTION_KEY` | from `.env` | already set (#160) |

- `SMTP_HOST` / `SMTP_PORT` are removed from `.env` and CI.
- `.env` gains `SMTP_ALLOW_PRIVATE_HOSTS=true`.

### Auth constants

`Pidgey\Tests\Support\Mailpit::AUTH_USER = 'pidgey-test'` and
`Mailpit::AUTH_PASSWORD = 'pidgey-test-password'`. They must match `MP_SMTP_AUTH` in
compose and CI literally.

### Release step

`darthjee/circleci_pidgey-base:0.0.4` must be pushed (`make build-ci-base && make
push-ci-base`) before CI on the PR can pass. The PR description states this. Pushing is done
by the repository owner, not by an agent.
