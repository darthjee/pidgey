# Product Owner Plan: Implement the pidgey-client core

Main plan: [plan.md](plan.md)

## Shared contracts

- **What you rely on:**
  - the public surface, the options and validation, and the client-local codes of [plan.md](plan.md#shared-contracts);
  - the spec paths `specs/lib/<Module>_spec.js` and `specs/lib/Version_spec.js`.
- Read the merged `clients/nodejs/lib/` before writing the as-built doc. The doc describes what was built, not the plan.

## Implementation Steps

### Step 1 — As-built doc

Rewrite `docs/agents/clients/nodejs.md`:
- drop "Today it is a scaffold" and replace "Current surface" with the as-built surface:
  - the exports (`PidgeyClient`, `PidgeyError`, `VERSION`);
  - the options with their defaults and validation;
  - the cleartext rule;
  - `send`: the request, the idempotency key, the success passthrough and `replayed`;
  - `PidgeyError` and the response body (cap, redaction, cut, non-enumerable fields);
  - the failure classification and `possiblySent`, including the `NETWORK_ERROR` cause-code rule;
  - 3xx handling with `redirect: 'manual'` (never followed, status kept);
  - abort and the per-attempt timeout;
  - the specs layout (`specs/lib/`, `specs/support/TestServer.js`);
- state that retries are not built yet (#248): one attempt per `send`, whatever `maxRetries` says;
- link the specs (`library.md`, `errors-and-retry.md`) for the rules instead of copying them.

The package fields table, Scripts, Pack check, Commands and CI stay. Update them only if they changed.

### Step 2 — Spec additions

- `docs/agents/specs/node-client/library.md`:
  - the `new PidgeyClient(options)` table and the "Configuration mistakes" list gain the rules decided in #247:
    - `timeoutMs`: a positive finite integer;
    - `retryDelayMs`: a non-negative finite integer;
    - `retryAmbiguous` and `allowInsecureHttp`: booleans when given;
  - under Redirects, note that the implementation uses `fetch`'s `redirect: 'manual'` to keep the status, with the same outcome (never followed, `INVALID_RESPONSE` with the status). Also adjust the `Redirects` row of the Request table.
- `docs/agents/specs/node-client/errors-and-retry.md`: the `CLIENT_CONFIGURATION` row of Client-local codes lists the same new cases.
- `docs/agents/specs/node-client/testing.md`, section Library: the spec table names the `specs/lib/` paths actually used (`specs/lib/PidgeyClient_spec.js`, `specs/lib/PidgeyClientSend_spec.js`, `specs/lib/PidgeyError_spec.js`, `specs/lib/Version_spec.js`, and the module specs added). `RetryPolicy_spec` and `OpenApi_spec` keep their planned names under `specs/lib/` and `specs/contract/`, marked as coming with #248 and #250.

## Files to Change
- `docs/agents/clients/nodejs.md` — the as-built core.
- `docs/agents/specs/node-client/library.md` — the option validation rules and the redirect implementation note.
- `docs/agents/specs/node-client/errors-and-retry.md` — the `CLIENT_CONFIGURATION` row.
- `docs/agents/specs/node-client/testing.md` — the spec paths of the Library table.

## Notes
- If `nodejs` changes a contract while implementing (for example the module names), follow the code and say so in the PR.
