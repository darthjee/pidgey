# Cli Plan: Add the clients_integration job

Main plan: [plan.md](plan.md)

## Shared contracts

- Relies on `clients/scripts/integration_support.mjs` (imported as `../../../scripts/integration_support.mjs`) and the environment of [plan.md](plan.md#environment-handed-to-the-specs-by-integrationsh).
- Produces `yarn test:integration` and keeps `specs/integration/` out of `test` / `coverage`.

## Implementation Steps

### Step 1 — Split the spec globs

In `package.json`: `test` → `jasmine "specs/cli/**/*_spec.js" "specs/bin/**/*_spec.js"`, `coverage` → `c8 jasmine` with the same globs, new `test:integration` → `jasmine "specs/integration/**/*_spec.js"`. Lint still covers `specs/`; the pack list is unchanged.

### Step 2 — Write `specs/integration/Send_spec.js`

Same skip / required rule as the library spec, through `integrationEnv()`. Spawn the real `bin/pidgey.js` with `process.execPath`, no shell, a minimal env (`PATH`, `PIDGEY_URL=env.url`, `PIDGEY_TOKEN=<token>`), and `--allow-insecure-http --to integration@example.com --subject <uniqueSubject('pidgey-cli')> --text <body>` (reuse the spawn pattern of `specs/bin/Pidgey_spec.js`, without the coverage env). The five cases of [plan.md](plan.md#cases-both-specs):

1. Exit `0`, empty stdout; `waitForMessage` finds the message.
2. `--idempotency-key <key>` with the same body → exit `0`; with `--verbose`, the result line reports the replay; still one Mailpit message.
3. Same key, different `--text` → exit `1`, stderr names `IDEMPOTENCY_KEY_REUSED`.
4. No-connection token → exit `1`, stderr names `NO_SMTP_CONNECTION`.
5. Revoked token → exit `1`, stderr names `UNAUTHORIZED`.

In every case, stdout and stderr never contain the token.

## Files to Change
- `clients/cli/package.json` — `test`, `coverage`, `test:integration` scripts.
- `clients/cli/specs/integration/Send_spec.js` — new spec.

## CI Checks
- `clients/cli`: `make clients-checks` (CI job: `clients_checks`) — still 100 % without the integration spec.
- `clients/cli`: `make clients-integration` (CI job: `clients_integration`).

## Notes
- If the `--verbose` line does not expose the replay, assert only exit `0` and the single Mailpit message.
