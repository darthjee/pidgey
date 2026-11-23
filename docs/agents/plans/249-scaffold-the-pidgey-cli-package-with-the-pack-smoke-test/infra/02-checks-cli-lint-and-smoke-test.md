# checks.sh: CLI, committed-file lint and pack smoke test

Extend `clients/scripts/checks.sh` per `docs/agents/specs/node-client/testing.md` (sections
CI and `make tests`, Pack smoke test) and `packaging.md` (Supply chain, Committed-file lint):

1. **Package list:** `PACKAGES="nodejs cli"`, the library first, so a library failure stops
   the run before the CLI is checked against it.
2. **Fresh library copy for the CLI:** before the CLI's `yarn install --frozen-lockfile`,
   remove `clients/cli/node_modules/pidgey-client`, so Yarn copies the current library source
   again; the local `node_modules` volume persists between runs and would otherwise keep a
   stale copy. Confirm the reinstall really happens (Yarn may skip the install when its
   integrity file says up to date: add `--check-files` if needed). Proof: change the
   library's `VERSION` locally, run the checks, see it in `pidgey --version`, revert.
3. **Committed-file lint**, for the CLI only, before its install: read
   `clients/cli/package.json` with `node` and fail with a clear message unless
   `dependencies["pidgey-client"]` is exactly `file:../nodejs`.
4. **Pack smoke test**, a function (or `clients/scripts/smoke_test.sh` called by
   `checks.sh`), run once after every package passed its checks and pack file-list check:
   - `WORK="$(mktemp -d)"` with cleanup on exit; tarballs in `$WORK/dist`, project in
     `$WORK/project`;
   - `npm pack --ignore-scripts --pack-destination "$WORK/dist"` in `clients/nodejs`;
     `pack_cli.sh "$WORK/dist"` for the CLI;
   - `npm init -y` in the project, then
     `npm install --offline --ignore-scripts --no-audit --no-fund <library tgz> <cli tgz>`;
   - assert `node_modules/pidgey-cli/package.json` `dependencies["pidgey-client"]` is exactly
     `^<version of clients/nodejs/package.json>`;
   - assert `node --input-type=module -e "import { PidgeyClient, PidgeyError } from 'pidgey-client'; …"`
     gets two functions;
   - assert `node -e "const c = require('pidgey-client'); …"` (CommonJS, so through
     `require(esm)`) exposes `PidgeyClient`;
   - assert `npx --no-install pidgey --version` prints exactly
     `pidgey-cli <clients/cli/package.json version> (pidgey-client <clients/nodejs/package.json version>)`;
   - assert `npx --no-install pidgey --help` exits `0`;
   - every failed assertion prints what was expected and what was found, and exits non-zero.
   Nothing is fetched (offline), so `pidgey-client` need not be on the registry; the
   registry check stays at publish time (#253).
5. Keep the per-package loop and the existing pack file-list check unchanged otherwise.

## Files to Change

- `clients/scripts/checks.sh` — package list, fresh library copy, committed-file lint, smoke test call.
- `clients/scripts/smoke_test.sh` — new, executable, if the smoke test is a separate script.
