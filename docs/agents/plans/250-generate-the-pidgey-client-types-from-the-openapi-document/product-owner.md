# Product-owner Plan: Generate the pidgey-client types from the OpenAPI document

Main plan: [plan.md](plan.md)

## Shared contracts

- Document what `nodejs`, `infra` and `cli` built, as described in [plan.md](plan.md#shared-contracts).

## Implementation Steps

### Step 1 — As-built docs

- `docs/agents/clients/nodejs.md`:
  - the generated `types/v1.ts` and `yarn generate:types`, with the rule that a `/v1` document change needs a regeneration;
  - `tsconfig.build.json`, `yarn build:types` and `dist/`;
  - `exports`/`files`;
  - `specs/support/Fixtures.js` and the rule on off-contract inline bodies;
  - `specs/contract/OpenApi_spec.js` and the consumer fixture.
- `docs/agents/clients.md`, Checks section: the library build step before the pack check, and the `.d.ts` check in the smoke test.
- `docs/agents/clients/cli.md`: the shim declares Node.js only, and the CLI types against the library's declarations.
- `docs/agents/specs/node-client/testing.md`: drop "**Comes with #250.**" from the `specs/contract/OpenApi_spec.js` row.
- `docs/agents/docker/volumes.md` (and the compose doc that describes the `clients` service, if any): the read-only `source/openapi` mount.

## Files to Change

- `docs/agents/clients/nodejs.md`
- `docs/agents/clients.md`
- `docs/agents/clients/cli.md`
- `docs/agents/specs/node-client/testing.md`
- `docs/agents/docker/volumes.md`

## Notes

- Describe what was built, so read the merged code of the other agents before writing.
