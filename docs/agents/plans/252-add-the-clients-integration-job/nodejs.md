# Nodejs Plan: Add the clients_integration job

Main plan: [plan.md](plan.md)

## Shared contracts

- Relies on `clients/scripts/integration_support.mjs` (imported as `../../../scripts/integration_support.mjs`) and the environment of [plan.md](plan.md#environment-handed-to-the-specs-by-integrationsh).
- Produces `yarn test:integration` and keeps `specs/integration/` out of `test` / `coverage`.

## Implementation Steps

### Step 1 — Split the spec globs

In `package.json`: `test` → `jasmine "specs/lib/**/*_spec.js" "specs/contract/**/*_spec.js"`, `coverage` → `c8 jasmine` with the same globs, new `test:integration` → `jasmine "specs/integration/**/*_spec.js"`. `yarn lint` keeps covering `specs/` (the integration spec is linted). The pack file list is unchanged (`specs/` is not packed).

### Step 2 — Write `specs/integration/Send_spec.js`

`const env = integrationEnv()` at load time (a throw fails the run under `PIDGEY_INTEGRATION=required`); when `null`, every `it` calls `pending('no integration stack: set PIDGEY_INTEGRATION_URL')`. Clients: `new PidgeyClient({ token, baseUrl: env.url, allowInsecureHttp: true, maxRetries: 0 })`. The five cases of [plan.md](plan.md#cases-both-specs), each with its own `uniqueSubject('pidgey-client')` and a recipient such as `integration@example.com`:

1. `send` resolves (`replayed: false`) and `waitForMessage` finds one message with that subject and recipient.
2. Same `idempotencyKey` and body → resolves with `replayed: true`; `findMessages` still returns one message.
3. Same key, different `text` → rejects with a `PidgeyError` `statusCode: 422`, `code: 'IDEMPOTENCY_KEY_REUSED'`.
4. No-connection token → `statusCode: 409`, `code: 'NO_SMTP_CONNECTION'`, `possiblySent: false`.
5. Revoked token → `statusCode: 401`, `code: 'UNAUTHORIZED'`.

Use a generous per-spec timeout (e.g. 20 s) for real SMTP delivery.

## Files to Change
- `clients/nodejs/package.json` — `test`, `coverage`, `test:integration` scripts.
- `clients/nodejs/specs/integration/Send_spec.js` — new spec.

## CI Checks
- `clients/nodejs`: `make clients-checks` (CI job: `clients_checks`) — still 100 % without the integration spec.
- `clients/nodejs`: `make clients-integration` (CI job: `clients_integration`).

## Notes
- Locally, `yarn test:integration` outside the stack must report the cases as pending, not failed.
