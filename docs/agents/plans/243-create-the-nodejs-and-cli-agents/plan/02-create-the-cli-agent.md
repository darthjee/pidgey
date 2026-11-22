# Create the cli agent

Write `.claude/agents/cli.md`, with the same layout and shared sections as `nodejs.md` (step 01), adapted to the CLI.

- **Frontmatter:** `name: cli`; `description: Pidgey command-line client specialist. Use for any task involving the pidgey-cli npm package and its pidgey command: flags, token file, environment fallbacks, stdin, exit codes, output, or anything under clients/cli/.`; `tools: Read, Edit, Write, Bash`.
- **Your scope:** everything inside `clients/cli/`: `bin/`, source, Jasmine specs, `package.json` (whose committed `pidgey-client` dependency is always `file:../nodejs`), `yarn.lock`, `eslint.config.mjs`, the package README.
- **Do NOT touch:** `clients/nodejs/` (`nodejs`), and the same list as `nodejs.md` otherwise.
- **Thin-layer rule:** no HTTP code and no retry logic; if a change needs to know what a status or code means, the classification belongs in the library. The CLI imports only the public exports of `pidgey-client` (`PidgeyClient`, `PidgeyError`, `VERSION`): never a relative `../nodejs/…` import, never a deep import. When a library change is needed, stop and report to `architect`; never work around the gap in the CLI.
- **Stack:** as `nodejs.md` (ESM, `engines` `>=22.12.0`, Yarn, Jasmine, `c8` at 100 %, ESLint 9 flat, `tsc --checkJs` with `typescript` as a devDependency), plus `node:util` `parseArgs` in strict mode (no `commander` / `yargs`).
- **Credentials:** the token never comes from argv (no `--token` flag); only `--token-file` or `PIDGEY_TOKEN`; never print the token.
- **Specs:** in-process `specs/cli/Arguments_spec.js`, `Body_spec.js`, `ExitCode_spec.js`; the subprocess spec `specs/bin/Pidgey_spec.js` only for what in-process specs cannot prove (real `bin`, exit status, pipes, signals, process lifetime).
- **Commands:** as `nodejs.md`, with the exact lines marked *(planned)*, to be filled in by #249.
- **Boundary rules:** the same five rules as `nodejs.md`, including library first at release.

## Files to Change
- `.claude/agents/cli.md` — new agent file.
