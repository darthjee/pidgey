# Cli Plan: Generate the pidgey-client types from the OpenAPI document

Main plan: [plan.md](plan.md)

## Shared contracts

- You rely on the library shipping `dist/lib/index.d.ts` through its `exports` `types` condition. The `file:../nodejs` copy holds it, because `checks.sh` builds the library before the CLI's install.

## Implementation Steps

### Step 1 — Drop the `pidgey-client` shim

- Remove the `declare module 'pidgey-client'` block from `clients/cli/types/node.d.ts`, and update its header comment. The Node.js declarations stay until `@types/node` is approved.
- Run `yarn typecheck` against the installed copy. If the library's declarations need DOM types (`AbortSignal`, `fetch`), add `"lib": ["es2022", "dom"]` to `clients/cli/tsconfig.json`, rather than adding them to the shim.
- No runtime change, and coverage stays at 100 %.

## Files to Change

- `clients/cli/types/node.d.ts`: drop the library declaration.
- `clients/cli/tsconfig.json`: `lib`, only if needed.

## CI Checks

- `clients/cli`: `yarn lint`, `yarn typecheck`, `yarn coverage` (CI job: `clients_checks`)

## Notes

- Locally, `checks.sh` already drops the stale `node_modules/pidgey-client` before the CLI's install. A local `yarn typecheck` outside `checks.sh` needs `yarn build:types` in `clients/nodejs` first.
