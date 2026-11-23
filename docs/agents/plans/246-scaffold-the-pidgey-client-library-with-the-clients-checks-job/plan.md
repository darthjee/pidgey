# Plan: Scaffold the pidgey-client library with the clients_checks job

Issue: [246-scaffold-the-pidgey-client-library-with-the-clients-checks-job.md](../../issues/246-scaffold-the-pidgey-client-library-with-the-clients-checks-job.md)

## Overview

Create `clients/nodejs/`, the `pidgey-client` package. It is ESM, at version `0.1.0`, has no runtime dependency, and ships stub `PidgeyClient` / `PidgeyError` classes with no `send`. It has its gates from the first commit: ESLint, `tsc` typecheck, Jasmine plus `c8` at 100 %, and a pack file-list check. One shared script, `clients/scripts/checks.sh`, runs them all. A new `clients_checks` CircleCI matrix job runs it, and so do `make clients-checks` and `make tests` (through a new compose `clients` service). The docs record the as-built state and finish #243's hand-off.

## Agents involved

- [nodejs](nodejs.md): the package.
- [infra](infra.md): `checks.sh`, compose, the `Makefile`, CircleCI.
- [product-owner](product-owner.md): the as-built docs and the #243 hand-off. The hand-off files are owned by `architect`, who reviews them.

## Shared contracts

**Package layout (nodejs produces, infra consumes)**

- The package root is `clients/nodejs/`.
- Source is under `lib/`: `lib/index.js` re-exports `PidgeyClient` and `PidgeyError` from `lib/PidgeyClient.js` and `lib/PidgeyError.js`.
- Specs are under `specs/`, named `*_spec.js`.
- `package.json`:
  - `"name": "pidgey-client"`, `"version": "0.1.0"`, `"type": "module"`, `"license": "MIT"`;
  - `"exports": { ".": "./lib/index.js" }`, `"engines": { "node": ">=22.12.0" }`;
  - a `files` allowlist (`["lib/"]`; npm adds `package.json` and `README.md` itself);
  - no `dependencies`, and no `preinstall` / `install` / `postinstall` / `prepare` scripts.
- Scripts that `checks.sh` calls by name, which must exit non-zero on failure:
  - `yarn lint`: ESLint over `lib specs`.
  - `yarn typecheck`: `tsc --allowJs --checkJs --noEmit` on `lib`. A `jsconfig.json` or `tsconfig.json` in the package is allowed.
  - `yarn coverage`: `c8` running Jasmine. It writes **`coverage/lcov.info`** and fails below 100 % lines, branches, functions and statements (`check-coverage: true`, `all: true`, `include: ["lib/**/*.js"]`).
- `yarn.lock` is committed. `yarn install --frozen-lockfile` must pass on a clean checkout.

**Expected pack file list (nodejs produces, infra's script compares)**

- The file is `clients/nodejs/pack-files.txt`.
- It holds one path per line, sorted with `LC_ALL=C sort`, with no blank or comment lines.
- The paths are exactly the `files[].path` values of `npm pack --dry-run --json`, run in `clients/nodejs/`.
- `pack-files.txt` itself is not packed. It is not under `lib/`.

**`clients/scripts/checks.sh` (infra)**

- It is called with no argument, from any working directory. It resolves `clients/` from its own location.
- It holds a package list, `PACKAGES="nodejs"`; #249 appends `cli` and adds its smoke test after the loop.
- For each package, in `clients/<pkg>/`, it runs:
  1. `yarn install --frozen-lockfile`;
  2. `yarn lint`, `yarn typecheck`, `yarn coverage`;
  3. the pack check: `npm pack --dry-run --json`, file paths extracted with `node` (no `jq`), sorted, then `diff` against `pack-files.txt`. It prints the diff and fails on any difference.
- It runs under `set -euo pipefail` and stops at the first failure with a non-zero exit. It writes nothing outside `clients/<pkg>/` (`node_modules/`, `coverage/`).

**Compose service `clients` (infra)**

- Image `darthjee/node:0.2.1`, the Node of `dockerfiles/vite_pidgey-base/`. It has no `build` and no `depends_on`.
- `working_dir: /home/node/app`.
- Volumes:
  - `./clients:/home/node/app/clients`;
  - `./docker_volumes/clients_nodejs_node_modules:/home/node/app/clients/nodejs/node_modules` (the folder is committed with a `.gitkeep`, per `.gitignore`).
- `make clients-checks` runs `docker compose run --rm clients clients/scripts/checks.sh`. `make tests` calls `$(MAKE) clients-checks` after the frontend line.

**CI job `clients_checks` (infra)**

- A job parameter `node_image` (string) is used as `docker: - image: << parameters.node_image >>`.
- The workflow matrix covers `cimg/node:22.12.0` and `darthjee/circleci_node:0.2.1`, with `filters: *all_tags` and no context.
- The steps are `checkout`, then `clients/scripts/checks.sh`.
- Only when `node_image` is `darthjee/circleci_node:0.2.1` (a `when` / `equal` condition) does it run `upload_codacy_coverage` with `report: clients/nodejs/coverage/lcov.info` and `prefix: clients/nodejs`.
- `coverage_final` gains `clients_checks` in `requires`. Requiring the base name waits for every matrix instance.
- The deploy jobs' `requires` lists are not changed: the clients are not deployed.

**Commands named in the docs (product-owner)**

- `make clients-checks`.
- `docker compose run --rm clients clients/scripts/checks.sh`.
- `docker compose run --rm clients sh -c "cd clients/nodejs && yarn <lint|typecheck|test|coverage>"`.
