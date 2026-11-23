# Plan: Generate the pidgey-client types from the OpenAPI document

Issue: [250-generate-the-pidgey-client-types-from-the-openapi-document.md](../../issues/250-generate-the-pidgey-client-types-from-the-openapi-document.md)

## Overview

`clients/nodejs` generates its schema types from `source/openapi/v1.json` into a committed `types/v1.ts`, references them from its JSDoc, and emits `.d.ts` declarations into a git-ignored `dist/` through an explicit `yarn build:types`. A contract spec validates request bodies, shared response fixtures and the generated file against the document with `ajv`. `checks.sh` builds the declarations before packing, the smoke test type-checks a consumer fixture against the installed tarball, compose mounts the document, the CLI drops its `pidgey-client` shim, and the as-built docs follow.

## Agents involved

- [nodejs](nodejs.md)
- [infra](infra.md)
- [cli](cli.md)
- [product-owner](product-owner.md)

## Shared contracts

- **Document path:** `source/openapi/v1.json`, read from `clients/nodejs` as `../../source/openapi/v1.json` (in CI and in the `clients` compose service).
- **Library scripts** (`clients/nodejs/package.json`):
  - `generate:types`: writes `types/v1.ts` from the document with the pinned `openapi-typescript`;
  - `build:types`: `rm -rf dist && tsc -p tsconfig.build.json`, emitting `dist/lib/*.d.ts` and `dist/types/v1.d.ts`.
- **Package shape:** `exports` is `{ ".": { "types": "./dist/lib/index.d.ts", "default": "./lib/index.js" } }` and `files` is `["lib/", "dist/"]`. `pack-files.txt` lists every emitted `.d.ts`.
- **Git-ignore:** `clients/*/dist/` (root `.gitignore`).
- **Consumer fixture:** `clients/nodejs/specs/types/consumer.ts`. It imports only `pidgey-client` and needs no `@types/node`: it compiles with `lib: ["es2022", "dom"]`, `strict`, `module`/`moduleResolution: nodenext` and `noEmit`.
- **Order in `checks.sh`:** the library's `yarn build:types` runs after its `yarn coverage` and before its pack file-list check. The CLI installs after that, so its `file:../nodejs` copy holds `dist/`.
