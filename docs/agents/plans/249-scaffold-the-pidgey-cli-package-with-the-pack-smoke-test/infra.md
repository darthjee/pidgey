# Infra Plan: Scaffold the pidgey-cli package with the pack smoke test

Main plan: [plan.md](plan.md)

## Shared contracts

What this agent relies on (produced by `cli`):

- `clients/cli/` with npm scripts `lint`, `typecheck`, `test`, `coverage`, a committed
  `yarn.lock` and `pack-files.txt`; `yarn coverage` writes `clients/cli/coverage/lcov.info`.
- Committed `dependencies["pidgey-client"]` is exactly `file:../nodejs`; `bin` is
  `{ "pidgey": "bin/pidgey.js" }`.
- `pidgey --version` → stdout exactly `pidgey-cli <cli version> (pidgey-client <library version>)`,
  exit `0`; `pidgey --help` → exit `0`.

What this agent produces:

- `clients/scripts/pack_cli.sh <out_dir>`: copies `clients/cli/` (without `node_modules/`,
  `coverage/`) to a `mktemp -d` directory, sets `dependencies["pidgey-client"]` to
  `^<version of clients/nodejs/package.json>` there, runs `npm pack --pack-destination <out_dir>`
  there, prints the tarball's absolute path, removes the staging directory on exit. Never
  writes under `clients/`. Reused by the publish (#253).
- Compose volume `./docker_volumes/clients_cli_node_modules:/home/node/app/clients/cli/node_modules`.
- `clients_checks`: a `clients/cli` Codacy upload (prefix `clients/cli`) on
  `darthjee/circleci_node:0.2.1` only, and a clean-working-tree step after `checks.sh`.

## Steps

- [01 — Staging-copy pack script](infra/01-staging-copy-pack-script.md)
- [02 — checks.sh: CLI, committed-file lint and pack smoke test](infra/02-checks-cli-lint-and-smoke-test.md)
- [03 — CI job and compose volume](infra/03-ci-and-compose.md)

## CI Checks

- `clients/`: `make clients-checks` (CI job: `clients_checks`, matrix `cimg/node:22.12.0` and `darthjee/circleci_node:0.2.1`)

## Notes

- Verify the pack smoke test on the floor image too: it runs `npm` and `npx` from the image,
  and `cimg/node:22.12.0` ships its own npm version.
- Manual checks before the PR opens, for the PR description (revert both afterwards):
  1. make `pack_cli.sh` write a wrong range (for example `~` instead of `^`) → the smoke test fails;
  2. change the committed dependency to `^0.1.0` → the committed-file lint fails.
- The `clients` compose service has no `git`, so the clean-tree assertion lives only in CI;
  `pack_cli.sh` and the smoke test must write only under `mktemp -d` directories by design.
- Do not touch `clients/cli/` or `clients/nodejs/` (package owners) or docs (`product-owner`).
