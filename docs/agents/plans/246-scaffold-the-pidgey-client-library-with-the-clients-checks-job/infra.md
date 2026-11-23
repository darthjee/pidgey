# Infra Plan: Scaffold the pidgey-client library with the clients_checks job

Main plan: [plan.md](plan.md)

## Shared contracts

- **Rely on (nodejs):**
  - `clients/nodejs/` with the scripts `lint`, `typecheck` and `coverage`. `coverage` writes `coverage/lcov.info` and enforces 100 %.
  - The committed `yarn.lock`.
  - `clients/nodejs/pack-files.txt`: sorted (`LC_ALL=C`), one path per line, the `files[].path` of `npm pack --dry-run --json`.
- **Produce:**
  - `clients/scripts/checks.sh`: no argument, any working directory, `PACKAGES="nodejs"`. For each package it runs `yarn install --frozen-lockfile`, `yarn lint`, `yarn typecheck`, `yarn coverage`, then the pack diff. It uses `set -euo pipefail`.
  - The compose service `clients`:
    - `darthjee/node:0.2.1`, `working_dir: /home/node/app`, no `depends_on`;
    - mounts `./clients:/home/node/app/clients` and `./docker_volumes/clients_nodejs_node_modules:/home/node/app/clients/nodejs/node_modules`.
  - `make clients-checks` runs `docker compose run --rm clients clients/scripts/checks.sh`. `make tests` calls it.
  - The CI job `clients_checks`:
    - a matrix over `node_image` (`cimg/node:22.12.0`, `darthjee/circleci_node:0.2.1`), with `filters: *all_tags` and no context;
    - the Codacy upload (`clients/nodejs/coverage/lcov.info`, prefix `clients/nodejs`) runs on `darthjee/circleci_node:0.2.1` only;
    - `coverage_final` requires it.

## Steps

- [01 — Write checks.sh](infra/01-write-checks-sh.md)
- [02 — Add the compose service and make targets](infra/02-add-the-compose-service-and-make-targets.md)
- [03 — Add the clients_checks CI job](infra/03-add-the-clients-checks-ci-job.md)

## CI Checks

- `clients/`: `make clients-checks` (CI job: `clients_checks`).
- `.circleci/`: run `circleci config validate` if the CLI is available. Otherwise the PR's pipeline is the check.

## Notes

- `cimg/node` images run as `circleci` and include `yarn`, so `checks.sh` must not assume `darthjee/circleci_node`-specific tools. Use `node` instead of `jq` to read the pack JSON.
- `upload_codacy_coverage` rewrites `SF:` paths relative to the job root. c8's lcov paths are relative to `clients/nodejs/` (for example `SF:lib/index.js`), and its third sed rule prefixes them with `clients/nodejs/`. Check the printed `SF:` lines in the first CI run. If c8 emits absolute paths, the first rule would yield `clients/nodejs/clients/nodejs/…`: then run the upload with the report path relative to the repo root, and adjust the command only if needed.
- Manual check before the PR opens, recorded in the PR description:
  - comment out one spec, so `checks.sh` fails coverage;
  - add a stray file under `lib/`, so the pack diff fails;
  - edit `package.json` without updating `yarn.lock`, so the frozen install fails.

  Revert all three.
- #249 extends `PACKAGES` and appends the committed-file lint and the smoke test after the loop. Keep the per-package body in a function, so that stays a small diff.
