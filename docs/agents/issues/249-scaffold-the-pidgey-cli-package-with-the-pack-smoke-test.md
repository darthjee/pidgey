# Issue: Scaffold the pidgey-cli package with the pack smoke test

## Description

- Parent epic: #219 (wave 2, created by #228).
- Specs:
  - `docs/agents/specs/node-client/packaging.md`: sections Packages, CLI to library dependency, Stack, Node.js version, Supply chain (Committed-file lint);
  - `docs/agents/specs/node-client/testing.md`: sections CLI, CI and `make tests`, Pack smoke test, Coverage threshold, Node.js version matrix;
  - `docs/agents/specs/node-client/cli.md`: sections Flags (`--help`, `--version`), Exit codes.
- Owners: `cli` (package), `infra` (`clients/scripts/`, CI, compose), `product-owner` (as-built docs), `architect` (the #243 hand-off files).
- Depends on #246, which is merged (fbf7378). #247 and #248 are merged too, so the library exports `PidgeyClient`, `PidgeyError` and `VERSION`. Blocks #250 and #251.
- Today:
  - there is no `clients/cli/` folder;
  - `clients/scripts/checks.sh` loops over `PACKAGES="nodejs"` and runs, per package, the frozen install, lint, typecheck, coverage and the pack file-list check;
  - `clients_checks` runs it on `cimg/node:22.12.0` and `darthjee/circleci_node:0.2.1`, and uploads only `clients/nodejs` coverage;
  - the `clients` compose service mounts `./clients` with a `node_modules` volume for `nodejs` only;
  - `.claude/agents/cli.md`, `docs/agents/folder-structure.md` and `docs/agents/architecture/repository-layout.md` still mark `clients/cli/` as *(planned)*.

## Problem

The CLI package does not exist. Its dependency on the library is `file:../nodejs` in the repository and `^<floor>` once published (#220, #222). That swap only stays correct if every build packs the CLI the way the publish will, and installs it with the packed library.

## Expected Behavior

- `clients_checks` runs the CLI checks and the pack smoke test on both Node.js versions. It fails on:
  - a packed CLI range other than `^<version of clients/nodejs/package.json>`;
  - a broken `exports` map, or a failing `require(esm)`;
  - a file added to or missing from either tarball;
  - a committed `pidgey-client` dependency other than `file:../nodejs`;
  - CLI coverage below 100 %.
- The working tree is unchanged after a pack.
- The smoke test needs neither the network nor `pidgey-client` on the registry.
- `pidgey --version` prints `pidgey-cli <version> (pidgey-client <version>)`, the library version taken from its `VERSION` export. `pidgey --help` prints the usage and exits `0`.
- Any other invocation, including no argument and the send flags not built yet, is a usage error: the usage on stderr, exit `2`. #251 adds the send flags, so the scaffold never answers "not implemented".
- The CLI always runs against the current library source, also locally where the `node_modules` volume persists between runs.

### Testing

- `clients/cli` specs at 100 %:
  - `specs/cli/Arguments_spec.js`: `--version`, `--help`, an unknown flag, no argument;
  - `specs/bin/Pidgey_spec.js`: the real `bin/pidgey` spawned for `--version` (exit `0`, output on stdout) and an unknown flag (exit `2`, usage on stderr).
- The smoke test passes on both matrix jobs of the PR.
- A CI step after `checks.sh` asserts `git status --porcelain` is empty, which proves no pack touched the working tree.
- A manual check before the PR opens, recorded in the PR description: the smoke test fails when the staging copy writes a wrong range, and `checks.sh` fails when the committed dependency is changed to `^0.1.0`.

## Solution

1. **Package** `clients/cli/` (owner `cli`):
   - `package.json`:
     - named `pidgey-cli`, version `0.1.0`, `bin` `{ "pidgey": "bin/pidgey" }`;
     - `"type": "module"`, `"engines": { "node": ">=22.12.0" }`;
     - a `files` allowlist, no install script;
     - `dependencies["pidgey-client"]` as `file:../nodejs`;
     - scripts `lint`, `typecheck`, `test`, `coverage`, as in `clients/nodejs`.
   - `yarn.lock`, ESLint 9 flat config, Jasmine, `c8` at 100 %, `typescript` as a devDependency.
   - `bin/pidgey` (shebang) calls a module under `lib/`, which parses with `node:util` `parseArgs` in strict mode. It imports only the public exports of `pidgey-client`.
   - A README: what the command is, "Node.js 22.12 or later", a pointer to the repository.
   - The committed expected pack list `clients/cli/pack-files.txt`.
2. **Staging-copy pack script** (owner `infra`), for example `clients/scripts/pack_cli.sh <out_dir>`:
   - it copies `clients/cli/` to a temporary directory, without `node_modules` and `coverage`;
   - it rewrites `dependencies["pidgey-client"]` there to `^<version of clients/nodejs/package.json>`;
   - it runs `npm pack` there and writes the tarball to `<out_dir>`;
   - it never writes under `clients/`. The publish (#253) uses the same script.
3. **`checks.sh`** (owner `infra`):
   - `PACKAGES="nodejs cli"`. The library is checked first.
   - Before the CLI's install, it drops the installed copy of `pidgey-client`, so the CLI is tested against the current library source and not a stale copy.
   - The **committed-file lint**: it fails if `clients/cli/package.json` declares `pidgey-client` as anything other than `file:../nodejs`.
   - The **pack smoke test**, after both pack file-list checks, in a temporary directory:
     - `npm pack` the library and pack the CLI through the staging-copy script;
     - in a fresh project, `npm install --offline --ignore-scripts --no-audit --no-fund` both tarballs;
     - assert the range in the packed CLI's `package.json`;
     - assert `import { PidgeyClient, PidgeyError } from 'pidgey-client'` and `require('pidgey-client')`;
     - assert `npx pidgey --version` prints the CLI and library versions, and `npx pidgey --help` exits `0`.
4. **CI and compose** (owner `infra`):
   - `clients_checks` uploads the `clients/cli` coverage to Codacy too (prefix `clients/cli`), on the current version only;
   - it asserts a clean working tree after `checks.sh`;
   - the `clients` compose service gains a `node_modules` volume for `clients/cli`.
5. **As-built docs** (owner `product-owner`):
   - `docs/agents/clients/cli.md`, indexed from `docs/agents/clients.md`, with the package, scripts, specs and commands;
   - `docs/agents/clients.md`: the `clients/cli/` row and the Checks section (package list, lint, smoke test);
   - `docs/agents/clients/nodejs.md`: the smoke test, and that a library version bump also updates `clients/cli/yarn.lock`;
   - `docs/agents/deployment/pipeline.md`: the CLI upload and the clean-tree step of `clients_checks`;
6. **Hand-off from #243** (owner `architect`):
   - fill in the *(planned)* commands of `.claude/agents/cli.md` (the `docker compose run … clients …` lines and `make clients-checks`);
   - drop the *(planned)* marker of `clients/cli/` in `docs/agents/folder-structure.md` and `docs/agents/architecture/repository-layout.md`.

Out of scope:
- the send flags, token file, stdin, exit-code mapping and the rest of the subprocess cases (#251);
- the generated types and the `.d.ts` check (#250);
- the pack and publish jobs, and the registry check (#253);
- `clients_integration`.

## Benefits

- Every build checks the exact tarball shape that will be published, before anything is on npm.
- A broken range, `exports` map or allowlist fails the PR that causes it, not the release.
- #250 and #251 build on a CLI package and checks that already exist.
