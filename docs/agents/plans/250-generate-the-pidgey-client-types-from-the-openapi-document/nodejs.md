# Nodejs Plan: Generate the pidgey-client types from the OpenAPI document

Main plan: [plan.md](plan.md)

## Shared contracts

- You produce:
  - the scripts `generate:types` and `build:types`;
  - `exports`, `files` and `pack-files.txt` as in [plan.md](plan.md#shared-contracts);
  - the fixture `specs/types/consumer.ts`, which `infra`'s smoke test compiles.
- You rely on `infra` to run `build:types` in `checks.sh`, and to mount `source/openapi` in compose.

## Steps

- [01 — Generated types and JSDoc](nodejs/01-generated-types-and-jsdoc.md)
- [02 — Declaration build and package shape](nodejs/02-declaration-build-and-package-shape.md)
- [03 — Shared fixtures and contract spec](nodejs/03-shared-fixtures-and-contract-spec.md)
- [04 — Consumer fixture and README](nodejs/04-consumer-fixture-and-readme.md)

## CI Checks

- `clients/nodejs`: `yarn lint`, `yarn typecheck`, `yarn coverage`, `yarn build:types` (CI job: `clients_checks`)
- whole clients: `make clients-checks`

## Notes

- Pin `openapi-typescript`, `ajv` and `ajv-formats` to exact versions. The up-to-date check compares generator output byte for byte, so a floating generator version would break it.
- The library stays at zero runtime `dependencies`.
- Coverage stays at 100 %. `types/` and `specs/` are outside the c8 `include`.
