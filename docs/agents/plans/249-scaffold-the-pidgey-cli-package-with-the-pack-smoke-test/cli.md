# Cli Plan: Scaffold the pidgey-cli package with the pack smoke test

Main plan: [plan.md](plan.md)

## Shared contracts

What this agent produces (consumed by `infra`'s `checks.sh` and smoke test):

- `clients/cli/package.json`: `"name": "pidgey-cli"`, `"version": "0.1.0"`, `"type": "module"`,
  `"bin": { "pidgey": "bin/pidgey.js" }`, `"engines": { "node": ">=22.12.0" }`,
  `"files": ["bin/", "cli/"]`, no install or pack script, and exactly
  `"dependencies": { "pidgey-client": "file:../nodejs" }`.
- npm scripts `lint`, `typecheck`, `test`, `coverage`; `yarn coverage` writes
  `coverage/lcov.info`.
- Committed `yarn.lock` and `pack-files.txt` (format of `clients/nodejs/pack-files.txt`).
- `pidgey --version` → stdout exactly `pidgey-cli <cli version> (pidgey-client <library version>)\n`,
  exit `0`; `pidgey --help` → usage on stdout, exit `0`; anything else → a one-line error and
  the usage on stderr, exit `2`.

## Implementation Steps

### Step 1 — Package skeleton and tooling

Mirror `clients/nodejs/` so both packages are checked the same way:

- `package.json` as in the contract. Scripts:
  - `lint`: `eslint bin cli specs`;
  - `typecheck`: `tsc --allowJs --checkJs --noEmit -p .`;
  - `test`: `jasmine "specs/**/*_spec.js"`;
  - `coverage`: `c8 jasmine "specs/**/*_spec.js"`.
  - `c8` block as in the library, with `include: ["bin/**/*.js", "cli/**/*.js"]`, `all: true`,
    `check-coverage: true` and 100 % lines, branches, functions, statements.
  - devDependencies: the same packages and ranges as `clients/nodejs/package.json`
    (`@eslint/js`, `c8`, `eslint`, `eslint-plugin-jasmine`, `eslint-plugin-jsdoc`, `globals`,
    `jasmine`, `typescript`). No new dependency, so no `security` review is needed.
- `eslint.config.mjs`: copy of the library's config (ignores `node_modules/**`, `coverage/**`).
- `tsconfig.json`: the library's, with `"include": ["bin", "cli"]`.
- `yarn.lock`: generated inside the compose service
  (`docker compose run --rm clients sh -c "cd clients/cli && yarn install"`); never on the host.
  The compose volume for `clients/cli/node_modules` comes from `infra`; until it exists, the
  install writes into the bind mount, which `.gitignore` already covers
  (`clients/*/node_modules/`).
- `README.md`: what `pidgey` is (the command-line client of Pidgey's `/v1` API, a thin layer
  over `pidgey-client`), "Node.js 22.12 or later", `npm install -g pidgey-cli`, that only
  `--version` and `--help` exist today, and a pointer to the repository.
- `pack-files.txt`: generated with the command documented in `docs/agents/clients/nodejs.md`
  (section Pack check), run from `clients/cli/` in the compose service, after Step 2 so every
  source file exists. Review the list by hand: only `README.md`, `bin/pidgey.js`, the `cli/`
  modules and `package.json`.

### Step 2 — `--version`, `--help` and the usage error, with specs

Source (one module per file, JSDoc on every export):

- `cli/version.js`: `export const VERSION = '0.1.0'`, same pattern as the library's
  `lib/version.js`; a spec asserts it equals `package.json` `version` (read with
  `node:fs` in the spec).
- `cli/Arguments.js`: `parseArguments(argv)` built on `node:util` `parseArgs` with
  `strict: true`, `allowPositionals: false`, options `help` and `version` (both boolean).
  Returns `{ help, version }`; throws a usage error (a small `UsageError extends Error`
  class, exported) on an unknown flag, a positional, a repeated flag (check the `tokens`
  from `parseArgs` with `tokens: true`, as `cli.md` makes a repeated flag a usage error) and
  on no flag at all. Also exports `USAGE`, the usage text listing `--help` and `--version`
  (#251 extends it).
- `cli/run.js`: `run(argv, { stdout, stderr })` returns the exit code:
  - `--version` → writes `pidgey-cli ${VERSION} (pidgey-client ${LIBRARY_VERSION})\n`, where
    `LIBRARY_VERSION` is `import { VERSION } from 'pidgey-client'`; returns `0`;
  - `--help` → writes `USAGE` to stdout; returns `0` (`--help` wins when both are given);
  - `UsageError` → writes `pidgey: <message>\n` then `USAGE` to stderr; returns `2`;
  - imports only the public exports of `pidgey-client`, never `../nodejs/...`.
- `bin/pidgey.js`: `#!/usr/bin/env node` shebang, calls `run(process.argv.slice(2), process)`
  and sets `process.exitCode` (not `process.exit`, so stdout is flushed). Executable bit
  set (`git update-index --chmod=+x` if needed).

Specs (mirroring the source tree, no `fdescribe`/`fit`):

- `specs/cli/Arguments_spec.js`: `--version`, `--help`, both, an unknown flag, `--token`
  (unknown), a positional, a repeated `--version`, no argument.
- `specs/cli/run_spec.js`: the exact `--version` line (CLI version from `package.json`,
  library version from `pidgey-client`'s `VERSION`), `--help` on stdout with exit `0`, a
  usage error on stderr with exit `2` and nothing on stdout. Use in-memory writable stubs.
- `specs/cli/version_spec.js`: `VERSION` equals `package.json` `version`.
- `specs/bin/Pidgey_spec.js`: spawn `process.execPath bin/pidgey.js` with
  `node:child_process` `spawn` (no shell) for `--version` (exit `0`, the line on stdout,
  empty stderr) and `--bogus` (exit `2`, usage on stderr, empty stdout). c8 follows the
  child through `NODE_V8_COVERAGE`, which covers `bin/pidgey.js`.

Loop lint → typecheck → coverage until all three are clean.

## Files to Change

- `clients/cli/package.json` — new.
- `clients/cli/yarn.lock` — new, generated.
- `clients/cli/eslint.config.mjs` — new.
- `clients/cli/tsconfig.json` — new.
- `clients/cli/README.md` — new.
- `clients/cli/pack-files.txt` — new.
- `clients/cli/bin/pidgey.js` — new, executable.
- `clients/cli/cli/Arguments.js`, `clients/cli/cli/run.js`, `clients/cli/cli/version.js` — new.
- `clients/cli/specs/cli/Arguments_spec.js`, `specs/cli/run_spec.js`,
  `specs/cli/version_spec.js`, `specs/bin/Pidgey_spec.js` — new.

## CI Checks

- `clients/cli`: `docker compose run --rm clients sh -c "cd clients/cli && yarn install --frozen-lockfile && yarn lint && yarn typecheck && yarn coverage"` (CI job: `clients_checks`)

## Notes

- `bin/pidgey.js` keeps the `.js` extension instead of the spec's indicative `bin/pidgey`:
  an extensionless file is not reliably loaded as ESM on the 22.12.0 floor. The command is
  still `pidgey`.
- `file:../nodejs` makes Yarn copy the library into `node_modules/pidgey-client`. If the
  installed copy looks stale while developing, delete `node_modules/pidgey-client` and
  reinstall; `checks.sh` does this on every run (infra).
- Do not touch `clients/nodejs/`, `clients/scripts/`, compose, CI or docs.
