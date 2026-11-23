# Stub classes, specs and README

Ship the minimal public surface, so that the 100 % threshold applies from now on. Classes only, with **no `send`**. #247 adds validation and `send`, so nothing here ever answers "not implemented".

- `lib/PidgeyError.js`: `export class PidgeyError extends Error`.
  - The constructor is `(message, { code } = {})`.
  - It sets `this.name = 'PidgeyError'` and `this.code`.
  - It has JSDoc. Keep the shape compatible with `docs/agents/specs/node-client/library.md`, which #247 extends with `status`, `possiblySent` and `smtp`.
- `lib/PidgeyClient.js`: `export class PidgeyClient`.
  - The constructor is `(options = {})` and stores a shallow copy of the options (for example as a frozen `options` property).
  - It has JSDoc. No validation, and no network.
- `lib/index.js`: re-exports both, with a module JSDoc.
- `specs/lib/PidgeyError_spec.js` and `specs/lib/PidgeyClient_spec.js`:
  - `instanceof Error` / `instanceof PidgeyError`, `name`, `code`, `message`, the default `code`;
  - the stored options, and the default `{}`.
- `specs/lib/index_spec.js`: the entry point exports both classes.
- `README.md`:
  - what `pidgey-client` is (the Node.js client for Pidgey's `/v1` API, in development);
  - "Node.js 22.12 or later";
  - a link to the repository;
  - a note that `0.1.0` is the first release line.

Run `docker compose run --rm clients sh -c "cd clients/nodejs && yarn lint && yarn typecheck && yarn coverage"` and reach 100 % on all four metrics.

## Files to Change
- `clients/nodejs/lib/index.js`: new.
- `clients/nodejs/lib/PidgeyClient.js`: new.
- `clients/nodejs/lib/PidgeyError.js`: new.
- `clients/nodejs/specs/lib/index_spec.js`: new.
- `clients/nodejs/specs/lib/PidgeyClient_spec.js`: new.
- `clients/nodejs/specs/lib/PidgeyError_spec.js`: new.
- `clients/nodejs/README.md`: new.
