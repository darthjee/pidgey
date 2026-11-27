# Plan: Add the clients_integration job

Issue: [252-add-the-clients-integration-job.md](../../issues/252-add-the-clients-integration-job.md)

## Overview

Add an end-to-end check of the Node clients against a real Pidgey: a dedicated compose app service (`integration_app`, own database, no user approval) next to MySQL and the plain test Mailpit; a seed script that creates the fixtures through the admin JSON API; one integration spec per package (library in `clients/nodejs`, CLI subprocess in `clients/cli`), kept out of the unit/coverage runs; `clients/scripts/integration.sh`, run by both `make clients-integration` (also part of `make tests`) and the new `clients_integration` CircleCI job on a machine executor.

## Agents involved

- [infra](infra.md): compose service, seed script, shared spec helper, `integration.sh`, `Makefile`, CircleCI, infra docs.
- [nodejs](nodejs.md): library integration spec and package scripts.
- [cli](cli.md): CLI integration spec and package scripts.
- [product-owner](product-owner.md): `testing.md` and the clients docs.

## Shared contracts

### Stack (infra produces)

| Service | Reached from the `clients` container at | Notes |
| --- | --- | --- |
| `integration_app` | `http://integration_app` (port 80) | `PHP_ENV=development`, `DB_NAME=pidgey_integration`, `USER_APPROVAL_REQUIRED=false`, `SMTP_ALLOW_PRIVATE_HOSTS=true`; no published host port. |
| `mailpit-plain` (existing) | SMTP `mailpit-plain:2025`, API `http://mailpit-plain:9025` | No auth, no TLS. |
| `mysql` (existing) | `mysql:3306` | `pidgey_%` grant already covers `pidgey_integration`. |

### Environment handed to the specs (by `integration.sh`)

| Variable | Value |
| --- | --- |
| `PIDGEY_INTEGRATION` | `required` (only set by `integration.sh`) |
| `PIDGEY_INTEGRATION_URL` | Pidgey base URL, `http://integration_app` |
| `PIDGEY_INTEGRATION_MAILPIT_URL` | `http://mailpit-plain:9025` |
| `PIDGEY_INTEGRATION_TOKEN` | `send` token of organization A (active Mailpit connection) |
| `PIDGEY_INTEGRATION_REVOKED_TOKEN` | a `send` token of organization A, revoked |
| `PIDGEY_INTEGRATION_NO_CONNECTION_TOKEN` | `send` token of organization B (no SMTP connection) |

The seed creates fresh organizations on every run (random suffix), so reruns against the same database never collide.

### Shared helper `clients/scripts/integration_support.mjs` (infra produces, both specs import by relative path)

- `integrationEnv()` → `{ url, mailpitUrl, token, revokedToken, noConnectionToken }` when `PIDGEY_INTEGRATION_URL` is set and every other variable is present; `null` when `PIDGEY_INTEGRATION_URL` is unset and `PIDGEY_INTEGRATION !== 'required'` (the spec then calls `pending(...)` for every case); **throws** naming the missing variable(s) when `PIDGEY_INTEGRATION === 'required'` and anything is missing. Never includes a token value in a message.
- `uniqueSubject(prefix)` → `"<prefix> <crypto.randomUUID()>"`.
- `findMessages(mailpitUrl, subject)` → array of Mailpit message summaries whose `Subject` equals `subject` (Mailpit HTTP API, `GET /api/v1/search?query=subject:"…"` or `GET /api/v1/messages`).
- `waitForMessage(mailpitUrl, subject, { timeoutMs = 10000 })` → resolves with the single matching summary, polling every 250 ms; rejects on timeout.

### Package scripts (nodejs and cli produce, `integration.sh` calls)

- `yarn test:integration` = `jasmine "specs/integration/**/*_spec.js"`.
- `yarn test` / `yarn coverage` no longer match `specs/integration/` (explicit directory globs), so integration never counts toward the 100 % threshold or the Codacy upload.

### Cases (both specs)

Every client is built with `allowInsecureHttp: true` (library) or `--allow-insecure-http` (CLI), since the stack is plain HTTP on a non-loopback host, and `maxRetries: 0` for the library.

1. Send with `PIDGEY_INTEGRATION_TOKEN` → success; Mailpit shows exactly one message with the unique subject and the recipient.
2. Same `Idempotency-Key`, same body → success with `replayed: true` (CLI: exit `0`); still exactly one Mailpit message.
3. Same `Idempotency-Key`, different body → `422 IDEMPOTENCY_KEY_REUSED` (CLI: exit `1`, code on stderr).
4. `PIDGEY_INTEGRATION_NO_CONNECTION_TOKEN` → `409 NO_SMTP_CONNECTION`, `possiblySent: false` (CLI: exit `1`).
5. `PIDGEY_INTEGRATION_REVOKED_TOKEN` → `401 UNAUTHORIZED` (CLI: exit `1`).

`409 IDEMPOTENCY_KEY_IN_USE` is out of scope (it needs a send still in flight).
