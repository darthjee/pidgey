# Issue: Add the clients_integration job

## Description
Implement the Integration and "CI and `make tests`" sections of `docs/agents/specs/node-client/testing.md`: an end-to-end spec that drives a real Pidgey (backend + MySQL + Mailpit) through both the `pidgey-client` library and the `pidgey` CLI, a shared `clients/scripts/integration.sh` script, a `clients_integration` CircleCI job and a `make clients-integration` target.

Parent epic: #219 (wave 2, created by #228). Owners: `infra` (script, job, `Makefile`, compose), `nodejs` and `cli` (the spec). Dependencies #251 (CLI) and #237 (`POST /v1/messages`) are closed. Blocks #253.

## Problem
The client specs only run against an in-process server. Nothing proves that the library and the CLI actually send a message through a real Pidgey and that it is delivered over SMTP.

## Expected Behavior
- `clients_integration` is green on a branch that touches `clients/`.
- `make tests` runs backend, frontend, `clients-checks` and `clients-integration`, and is green locally.
- Locally, the spec is skipped unless the environment points at a running stack; with `PIDGEY_INTEGRATION=required` (set by `integration.sh`) a missing stack is a failure, not a skip.
- Integration coverage is not merged into the 100 % threshold and is not uploaded to Codacy.

## Solution
1. **Spec** (`specs/integration/Send_spec.js`, path indicative):
   - **Seeding** goes through Pidgey's admin JSON API over HTTP, never the database: `POST /auth/register.json` (organization + session), `POST /smtp-connections.json` pointing at Mailpit then `POST /smtp-connections/:id/activate.json`, `POST /api-tokens.json` for a `send`-scoped token, and `DELETE /api-tokens/:id.json` to revoke one. A second organization is left without an active connection.
   - **Library and CLI**: every case runs through `pidgey-client` **and** through the `pidgey` CLI, spawned as a subprocess of `clients/cli`'s bin with the URL and token as flags or environment (the packed-tarball path stays the smoke test's job).
   - **Cases**:
     - a real send, asserted as delivered through Mailpit's HTTP API (`GET /api/v1/messages`);
     - a real `409 NO_SMTP_CONNECTION` for the organization without an active connection;
     - a real `409 IDEMPOTENCY_KEY_IN_USE`: the same `Idempotency-Key` reused with a different body;
     - a real `401` for a revoked token.
2. **`clients/scripts/integration.sh`**: runs the spec with the "required" flag set. The `Makefile` target and the CI job call this same script.
3. **`clients_integration` CircleCI job**: machine executor, boots Pidgey + MySQL + Mailpit with `docker compose` (same stack and commands as the `Makefile` target), current Node.js version only, `requires: [clients_checks]`. It holds no publishing credential.
4. **`Makefile`**: `make clients-integration` boots the compose stack (migrated DB, `SMTP_ALLOW_PRIVATE_HOSTS` so Pidgey can reach Mailpit) and runs `integration.sh`; `make tests` runs it too (accepted: slower local run).
5. **Docs**: `docs/agents/deployment/pipeline.md` gains the job; `docs/agents/clients.md` documents how to run the integration spec; `docs/agents/specs/node-client/testing.md` (Integration, item 4) lists both `409` cases and the admin-API seeding.
