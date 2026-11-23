# Plan: Scaffold the pidgey-cli package with the pack smoke test

Issue: [249-scaffold-the-pidgey-cli-package-with-the-pack-smoke-test.md](../../issues/249-scaffold-the-pidgey-cli-package-with-the-pack-smoke-test.md)

## Overview

Create `clients/cli/` (`pidgey-cli`, command `pidgey`) with only `--version` and `--help`,
checked at 100 % coverage like the library. Add the staging-copy pack script, the
committed-file lint and the pack smoke test to `clients/scripts/`, run the CLI in
`clients_checks` on both Node.js versions with its own Codacy upload, and document what was
built. `cli` builds the package first, `infra` wires the scripts, CI and compose around it,
`product-owner` writes the as-built docs and the #243 hand-off last.

## Agents involved

- [cli](cli.md)
- [infra](infra.md)
- [product-owner](product-owner.md)

## Shared contracts

### Package `clients/cli/` (produced by `cli`, consumed by `infra`)

- `package.json`: `"name": "pidgey-cli"`, `"version": "0.1.0"`, `"type": "module"`,
  `"bin": { "pidgey": "bin/pidgey.js" }`, `"engines": { "node": ">=22.12.0" }`,
  `"files": ["bin/", "cli/"]`, no `preinstall`/`install`/`postinstall`/`prepack`/`postpack`
  script, and exactly `"dependencies": { "pidgey-client": "file:../nodejs" }`.
- npm scripts with the same names as the library, called by `checks.sh`: `lint`,
  `typecheck`, `test`, `coverage`. `yarn coverage` writes `clients/cli/coverage/lcov.info`.
- `clients/cli/yarn.lock` committed; `yarn install --frozen-lockfile` passes.
- `clients/cli/pack-files.txt`: the sorted output of `npm pack --dry-run --json` file paths,
  same format as `clients/nodejs/pack-files.txt`. Expected content:
  `README.md`, `bin/pidgey.js`, `cli/*.js` (each source module), `package.json`.
- Behaviour, asserted by the smoke test:
  - `pidgey --version` → stdout exactly `pidgey-cli <cli version> (pidgey-client <library version>)\n`,
    exit `0`. The library version is the `VERSION` export of `pidgey-client`.
  - `pidgey --help` → the usage on stdout, exit `0`.
  - anything else (no argument, unknown flag, positional argument, repeated flag) → the
    usage on stderr preceded by a one-line error, exit `2`.

### Staging-copy pack script (produced by `infra`, reused by #253)

- `clients/scripts/pack_cli.sh <out_dir>`, callable from any working directory.
- Copies `clients/cli/` (without `node_modules/` and `coverage/`) to a `mktemp -d` directory,
  sets `dependencies["pidgey-client"]` there to `^<version of clients/nodejs/package.json>`,
  runs `npm pack --pack-destination <out_dir>` there, prints the tarball's absolute path on
  stdout, removes the staging directory on exit.
- Never writes under `clients/`.

### Compose and CI (produced by `infra`, documented by `product-owner`)

- Compose volume `./docker_volumes/clients_cli_node_modules:/home/node/app/clients/cli/node_modules`.
- `clients_checks`: a second `upload_codacy_coverage` with `report: clients/cli/coverage/lcov.info`,
  `prefix: clients/cli`, on `darthjee/circleci_node:0.2.1` only; a step after `checks.sh`
  failing when `git status --porcelain` prints anything.
