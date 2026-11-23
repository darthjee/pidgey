# Issue: Scaffold the pidgey-client library with the clients_checks job

## Description

- Parent epic: #219 (wave 2, created by #228).
- Specs:
  - `docs/agents/specs/node-client/packaging.md`: sections Packages, Stack, Node.js version, Versioning, Supply chain;
  - `docs/agents/specs/node-client/testing.md`: sections CI and `make tests`, Coverage threshold, Node.js version matrix.
- Owners: `nodejs` (package), `infra` (`clients/scripts/`, CI, `Makefile`, compose), `product-owner` (as-built docs), `architect` (the #243 hand-off files).
- Depends on #243 (the agents), which is merged. Blocks #247 and #249.
- Today:
  - there is no `clients/` folder;
  - docker-compose has no Node service for it (`frontend_dev` mounts `./frontend` and depends on `app`);
  - `make tests` runs the backend `tests` container and `frontend_dev`;
  - CI has `backend_checks`, `backend_tests`, `frontend_checks` and `coverage_final` (which requires `backend_tests` and `frontend_checks`).

## Problem

`docs/agents/contributing.md` requires the check job of a new application folder to exist before changes to that folder are merged. The library needs its package skeleton and its checks from its first commit, including #220's 100 % coverage threshold and Node.js matrix, so no later change retrofits them onto merged code.

## Expected Behavior

- `clients_checks` is green on both Node.js versions (floor `cimg/node:22.12.0`, current `darthjee/circleci_node:0.2.1`). It fails:
  - below 100 % coverage (lines, branches, functions or statements);
  - on a lint or typecheck error;
  - on a lockfile that is out of date (`--frozen-lockfile`);
  - on a file added to or missing from the pack, compared with the committed expected list.
- It runs on every branch and tag (like the other check jobs), in parallel with `backend_checks`, `backend_tests` and `frontend_checks`; `coverage_final` also requires it.
- `make clients-checks` and `make tests` run the same `checks.sh`.
- `docs/agents/clients.md` and `docs/agents/clients/nodejs.md` exist and are indexed from `AGENTS.md` and `docs/agents/specs.md`.
- The package has no runtime dependency and no install script.

### Testing

- `yarn coverage` covers the stubs at 100 %.
- `make clients-checks` passes locally, and `clients_checks` passes on both matrix jobs of the PR.
- A manual check before the PR opens, recorded in the PR description: an uncovered branch fails coverage, and a file added outside the expected list fails the pack check.

## Solution

1. **Package** `clients/nodejs/` (owner `nodejs`):
   - `package.json`:
     - named `pidgey-client`, version **`0.1.0`** (the first real release line; nothing publishes before #253's tag);
     - `"type": "module"`, an `exports` map, `"engines": { "node": ">=22.12.0" }`;
     - a `files` allowlist, no runtime dependency, no install script;
     - scripts `lint`, `test`, `coverage`, `typecheck` (`tsc --allowJs --checkJs --noEmit`).
   - `yarn.lock`, ESLint 9 flat config (`eslint.config.mjs`), Jasmine, and `c8` with `check-coverage: true` at **100 %** lines, branches, functions and statements.
   - A README: what the package is, "Node.js 22.12 or later", a pointer to the repository.
   - **Stub surface: classes only, no `send`.**
     - `PidgeyError extends Error` sets `name` and `code`.
     - `PidgeyClient` stores its options.
     - Both carry JSDoc and specs. #247 adds options validation and `send`, so no stub ever answers "not implemented".
   - The committed **expected pack file list** (for example `clients/nodejs/pack-files.txt`).
2. **Script** `clients/scripts/checks.sh` (owner `infra`). For each package, it runs:
   - `yarn install --frozen-lockfile`;
   - `yarn lint`, `yarn typecheck`, `yarn coverage`;
   - `npm pack --dry-run`, then compares the file list with the expected list.

   It loops over a package list, so #249 adds `clients/cli` and the smoke test without reshaping it.
3. **CI** `clients_checks` in `.circleci/config.yml` (owner `infra`):
   - It runs `checks.sh` as a matrix over the floor (`cimg/node:22.12.0`) and current (`darthjee/circleci_node:0.2.1`) images.
   - It has `filters: *all_tags` and no context.
   - On the current version only, it uploads `clients/nodejs` coverage to Codacy with `upload_codacy_coverage` (prefix `clients/nodejs`).
   - `coverage_final` requires it.
4. **Compose and Makefile** (owner `infra`):
   - A new compose service **`clients`** on `darthjee/node:0.2.1` (the image of `dockerfiles/vite_pidgey-base/`):
     - it mounts `./clients`, with a `node_modules` volume per package under `docker_volumes/`;
     - it has no `depends_on`.
   - `make clients-checks` runs `checks.sh` in that service (development version only).
   - `make tests` runs it too.
5. **As-built docs** (owner `product-owner`):
   - the hub `docs/agents/clients.md`, and `docs/agents/clients/nodejs.md` (scaffold, scripts, checks);
   - the `Clients` row of `AGENTS.md`, and the `clients` row of `docs/agents/specs.md`;
   - `docs/agents/deployment/pipeline.md` gains `clients_checks`;
   - the docker docs gain the `clients` service and `make clients-checks`;
   - `docs/agents/contributing.md` gains the `clients/` → `clients_checks` row of its check-jobs table.
6. **Hand-off from #243** (with `architect`, who owns those files):
   - fill in the *(planned)* commands of `.claude/agents/nodejs.md` (the exact `docker compose run … clients …` lines and `make clients-checks`);
   - drop the *(planned)* marker of `clients/`, `clients/nodejs/` and `clients/scripts/` in `docs/agents/folder-structure.md` and `docs/agents/architecture/repository-layout.md`.

Out of scope:
- the CLI package, the staging-copy pack script, the committed-file lint and the pack smoke test (#249);
- the generated types and the `.d.ts` check (#250);
- `send`, options validation and error mapping (#247);
- `clients_integration` and the publish jobs.

## Benefits

- `clients/` is checked from its first commit, as `contributing.md` requires.
- The 100 % threshold, the Node.js matrix and the pack check apply from the start, so #247–#250 build on gates that already exist.
- #249 extends `checks.sh` and the matrix instead of creating them.
