# Product-owner Plan: Add the clients_integration job

Main plan: [plan.md](plan.md)

## Shared contracts

Documents the stack, environment, helper, scripts and cases of [plan.md](plan.md#shared-contracts) as built.

## Implementation Steps

### Step 1 — Update `docs/agents/specs/node-client/testing.md`

In "Integration": the spec is one file per package (`clients/nodejs/specs/integration/Send_spec.js`, `clients/cli/specs/integration/Send_spec.js`) sharing `clients/scripts/integration_support.mjs`; item 2: seeding goes through the admin JSON API (`integration_seed.mjs`), on a dedicated `integration_app` with its own database and `USER_APPROVAL_REQUIRED=false`; item 4: the cases become `409 NO_SMTP_CONNECTION`, the idempotent replay, `422 IDEMPOTENCY_KEY_REUSED` and `401` for a revoked token, with `409 IDEMPOTENCY_KEY_IN_USE` explicitly out of scope (needs an in-flight send). State that `specs/integration/` is excluded from `yarn test` / `yarn coverage` and run by `yarn test:integration`.

### Step 2 — Update the clients docs

- `docs/agents/clients.md`: a new "Integration" section (what it proves, `make clients-integration`, the environment variables, the skip/required rule, how to run one package's spec against a running stack); the Owners table adds `integration_app` and the new scripts to `infra`; "Not built yet" drops "the integration job".
- `docs/agents/clients/nodejs.md` and `docs/agents/clients/cli.md`: mention `yarn test:integration` and the integration spec next to the existing scripts.

## Files to Change
- `docs/agents/specs/node-client/testing.md` — Integration section.
- `docs/agents/clients.md` — Integration section, owners, not-built list.
- `docs/agents/clients/nodejs.md`, `docs/agents/clients/cli.md` — `test:integration`.

## Notes
- Write after infra, nodejs and cli, so the docs match the actual paths and names.
