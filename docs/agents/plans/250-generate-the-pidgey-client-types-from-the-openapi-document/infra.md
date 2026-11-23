# Infra Plan: Generate the pidgey-client types from the OpenAPI document

Main plan: [plan.md](plan.md)

## Shared contracts

- You rely on these from `nodejs`:
  - the `build:types` script;
  - the fixture `clients/nodejs/specs/types/consumer.ts`;
  - the package shape described in [plan.md](plan.md#shared-contracts).
- You produce:
  - the `checks.sh` order: build, then the pack check, then the CLI;
  - the `.d.ts` check in the smoke test;
  - the compose mount of `source/openapi`;
  - the `clients/*/dist/` git-ignore.

## Implementation Steps

### Step 1 — Build step, git-ignore and compose mount

- **`checks.sh`:** in `check_package`, when the package is `nodejs`, run `yarn build:types` after `yarn coverage` and before `check_pack`. The CLI, checked next, then copies a library that holds `dist/`.
- **Root `.gitignore`:** add `clients/*/dist/`, so the clean-tree step of `clients_checks` stays green.
- **`docker-compose.yml`:** add `./source/openapi:/home/node/app/source/openapi:ro` to the `clients` service. With it, `../../source/openapi/v1.json` resolves from `clients/nodejs`, for the contract spec and for `yarn generate:types`.

### Step 2 — `.d.ts` check in the smoke test

In `clients/scripts/smoke_test.sh`, after the existing import and CLI assertions:

- copy `${CLIENTS_DIR}/nodejs/specs/types/consumer.ts` into `${WORK}/project/`;
- write `${WORK}/project/tsconfig.json` with:
  - `strict`, `noEmit`, `skipLibCheck: false`;
  - `module`/`moduleResolution: nodenext`, `target: es2022`, `lib: ["es2022", "dom"]`, `types: []`;
  - `include: ["consumer.ts"]`;
- run `"${CLIENTS_DIR}/nodejs/node_modules/.bin/tsc" -p "${WORK}/project"`, offline, against the installed `node_modules/pidgey-client`;
- on failure, print `Smoke test failed: consumer.ts does not type-check against the installed pidgey-client` and exit 1.

The project needs `"type": "module"` or a `.mts` for nodenext ESM resolution. Set `"type": "module"` in the project's `package.json` after `npm init -y`, or rename the copy to `consumer.mts`.

## Files to Change

- `clients/scripts/checks.sh`: `yarn build:types` for `nodejs`.
- `clients/scripts/smoke_test.sh`: the consumer type-check.
- `.gitignore`: `clients/*/dist/`.
- `docker-compose.yml`: the read-only `source/openapi` mount on `clients`.

## CI Checks

- `make clients-checks` (CI job: `clients_checks`, both Node.js versions, plus its clean-tree step).

## Notes

- Before opening the PR, check manually and record the results in the PR description:
  - a wrong `types` path in `exports` fails the smoke test;
  - editing a field of `source/openapi/v1.json` fails the contract spec.
  - Revert both.
- CI checks out the whole repository, so `clients_checks` needs no change for the document path.
