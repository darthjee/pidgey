# Issue: Generate the pidgey-client types from the OpenAPI document

## Description

- Parent epic: #219 (wave 2, created by #228).
- Specs:
  - `docs/agents/specs/node-client/openapi.md`: sections Generated types, Contract tests;
  - `docs/agents/specs/node-client/testing.md`: the `specs/contract/OpenApi_spec.js` row, sections Pack smoke test and `.d.ts` type check;
  - `docs/agents/specs/node-client/packaging.md`: section Supply chain (What ships, Pack check).
- Owners: `nodejs` (library), `infra` (`clients/scripts/`, compose), `cli` (its type shim), `product-owner` (as-built docs).
- Depends on #244 (the document, `source/openapi/v1.json`, OpenAPI 3.1 with the schemas `SendMessageRequest`, `SendMessageResponse`, `SmtpReply` and `Error`), #247 and #249, all merged. Blocks nothing in wave 2; #253 packs what this issue adds.
- Today:
  - `clients/nodejs` is plain JavaScript with hand-written JSDoc: `send` takes `{ to?: unknown, … }` and returns `Promise<Record<string, any>>`. `exports` is `{ ".": "./lib/index.js" }`, `files` is `["lib/"]`, and no `.d.ts` ships.
  - The specs build their response bodies inline (`json: { … }` in `PidgeyClientSend_spec.js`, `PidgeyClientSendAbort_spec.js`, `RetryPolicy_spec.js`, `RetryPolicyAbort_spec.js`), and nothing checks them against the document.
  - `clients/cli/types/node.d.ts` declares `module 'pidgey-client'` by hand, because the library ships no types.
  - `clients/scripts/smoke_test.sh` installs both tarballs offline and checks the imports and `pidgey --version`/`--help`, but no types.
  - The `clients` compose service mounts only `./clients`, so `source/openapi/v1.json` is not visible in it.

## Problem

Consumers get no TypeScript declarations, and nothing ties the client to the API contract: a change to `/v1` that the client does not follow, or a spec fixture that the real server would never send, goes unnoticed.

## Expected Behavior

- The tarball ships `.d.ts` declarations. Their message, response and error shapes come from the OpenAPI document, and `exports` points to them through a `types` condition.
- The library keeps zero runtime dependencies, and its source stays plain JavaScript.
- `clients_checks` fails when:
  - a request body built by `send` or a response fixture does not validate against the document;
  - the committed generated types differ from what the document generates;
  - the consumer fixture does not compile against the **installed** tarball (a wrong `types` path, a `.d.ts` missing from `files`);
  - a `.d.ts` is added to or missing from the pack file list.
- The working tree stays unchanged after `checks.sh`: the build output is ignored by git.
- The CLI types against the library's shipped declarations instead of its hand-written `pidgey-client` shim.
- Coverage stays at 100 %.

### Testing

- `specs/contract/OpenApi_spec.js` (library):
  - every request body that `send` builds in the specs (text only, html only, both, with an idempotency key) validates against `SendMessageRequest`;
  - every shared response fixture validates against the schema of its status;
  - the committed generated file equals what the pinned generator produces from `source/openapi/v1.json`.
- Shared fixtures `specs/support/Fixtures.js`: the valid success and error bodies, used by the existing specs instead of the inline ones. Bodies that are off-contract on purpose (forward compatibility: an extra field, an unknown code, a missing `smtp`) stay inline, named as such, and are not validated.
- The TypeScript consumer fixture `clients/nodejs/specs/types/consumer.ts`: every `PidgeyClient` option, `send` with a `signal` and an `idempotencyKey`, reading the response, and narrowing `err instanceof PidgeyError` to read `code`, `possiblySent` and `smtp`.
- The smoke test compiles it with `tsc --noEmit` (`strict`, `module`/`moduleResolution: nodenext`) inside the offline project where the tarballs are installed.
- A manual check before the PR opens, recorded in the PR description: the smoke test fails with a wrong `types` path in `exports`, and the contract spec fails after editing a field of the document.

## Solution

1. **Generated types** (owner `nodejs`):
   - `openapi-typescript`, pinned exactly, as a devDependency; a `generate:types` script writes `clients/nodejs/types/v1.ts` from `../../source/openapi/v1.json`. The file is committed.
   - The public JSDoc references it (`import('../types/v1.js').components['schemas'][…]`) for the message, the success response and the error fields.
2. **Declaration build** (owner `nodejs`):
   - a `build:types` script: `tsc -p tsconfig.build.json` (`allowJs`, `declaration`, `emitDeclarationOnly`, `include: ["lib", "types"]`, `outDir: "dist"`), after an `rm -rf dist`;
   - it emits `dist/lib/*.d.ts` and `dist/types/v1.d.ts`, so the relative imports of the declarations resolve inside the tarball. The `types/` source itself never ships;
   - `exports`: `{ ".": { "types": "./dist/lib/index.d.ts", "default": "./lib/index.js" } }`; `files`: `["lib/", "dist/"]`; `pack-files.txt` lists the `.d.ts` files;
   - `dist/` is git-ignored (`clients/*/dist/`).
   - Rejected: a `prepack` hook, because the smoke test packs with `--ignore-scripts` and a skipped hook would ship a tarball without types. A missing build fails the pack file-list check instead.
3. **Contract spec** (owner `nodejs`): `specs/contract/OpenApi_spec.js` and `specs/support/Fixtures.js` as in Testing, validated with `ajv` (`ajv/dist/2020`, pinned exactly, devDependency): the whole document is added as one schema, and each body is checked through a `$ref` into `components/schemas`.
4. **Checks** (owner `infra`):
   - `checks.sh` runs `yarn build:types` for the library before its pack file-list check, so the CLI's `file:` copy and the smoke test pack fresh declarations;
   - the smoke test copies `specs/types/consumer.ts` into its offline project and compiles it with the library's own `tsc` (`node_modules/.bin/tsc` of `clients/nodejs`), against a tsconfig it writes there;
   - the `clients` compose service mounts `./source/openapi` read-only, so the contract spec and the generator see the document.
5. **CLI** (owner `cli`): drop `declare module 'pidgey-client'` from `clients/cli/types/node.d.ts`; `yarn typecheck` resolves the library's declarations from its `file:` copy.
6. **As-built docs** (owner `product-owner`):
   - `docs/agents/clients/nodejs.md`: the generated types, the scripts, the build output, the contract spec and fixtures, and that a `/v1` document change needs `yarn generate:types`;
   - `docs/agents/clients.md`: the Checks section (build step, `.d.ts` check in the smoke test);
   - `docs/agents/clients/cli.md`: the shim no longer declares the library;
   - `testing.md`: drop "Comes with #250" from the contract spec row;
   - `docs/agents/compose.md` (or where the `clients` service is described): the new mount.

Out of scope:
- the pack and publish jobs (#253), which must run `yarn build:types` before packing;
- `@ts-expect-error` negative cases and a CommonJS consumer fixture (not adopted by `testing.md`);
- the CLI's own send flags (#251);
- replacing the CLI shim's Node.js declarations with `@types/node`.

## Benefits

- TypeScript consumers get declarations that follow the API contract.
- A `/v1` change the client does not follow fails the client's PR, not a user's build.
- The specs can no longer drift from what the server really sends.
