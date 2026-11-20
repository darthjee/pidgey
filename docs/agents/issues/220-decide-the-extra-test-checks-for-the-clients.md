# Issue: Decide the extra test checks for the clients

## Description

Epic #219 ("Create Clients") pins the core testing approach for `clients/nodejs/` (`pidgey-client`) and `clients/cli/` (`pidgey-cli`): unit specs that run real `fetch` against an in-process `node:http` server, an OpenAPI contract spec, lint, the `npm pack --dry-run` file-list check (#225), the `clients_checks` CI job, and an end-to-end `clients_integration` job against Pidgey + MySQL + Mailpit. This issue decides the **extra** checks the packages carry, and how the client tests run in CircleCI, and records them in the epic's spec files. It is a docs-only enhancement of the specs; the checks themselves are built by the later implementation sub-issues of #219.

## Problem

`docs/agents/specs/node-client/testing.md` still carries `**Pending decision** (#220)` for five candidate checks (pack smoke test, `.d.ts` type check, CLI subprocess tests, coverage threshold, Node.js version matrix), and its "No-hang expectation" (left by #231) has no test home until the subprocess-test decision is made. It also does not say how the client jobs run in CircleCI (what receives the requests in unit specs, how `clients_integration` boots Pidgey), nor how the `Makefile` and CI share commands. Without these decisions the implementation sub-issues have no fixed test contract, and `make tests` and CI could drift.

## Expected Behavior

- Every candidate check has a decision (adopt / reject, with rationale) in `testing.md`; for each adopted check, its package, command, `Makefile` target and CI job.
- The "No-hang expectation" is settled by the CLI subprocess tests.
- The CircleCI test infrastructure (unit and subprocess specs, `clients_integration`) is specified.
- `make tests` and CI call the same scripts, and the hub's pending table no longer lists #220.

## Solution

### CI test infrastructure

- **Unit and subprocess specs: no mock container.** The receiver is the spec itself: an in-process `node:http` server on `127.0.0.1`, port `0`, started per spec. It records every request (method, path, headers, body) and is scripted per spec for the reply (envelope errors, delays, a reset after the request is written, a non-JSON or oversized body, an idle keep-alive socket). It runs in `clients_checks` on a single `darthjee/circleci_node` container, like `frontend_checks`. Rejected: a mock server image (WireMock, MockServer, an echo server) as a secondary container, since it cannot easily script those transport failures, makes specs slower and flakier, and adds an image to maintain.
- **`clients_integration`: Pidgey receives, Mailpit lists.** A real Pidgey delivers over SMTP to Mailpit, and the spec asserts through Mailpit's HTTP API (`GET /api/v1/messages`). The job runs on a CircleCI **machine executor** and boots the stack with `docker compose` (Pidgey backend, MySQL, Mailpit), the same stack and commands as the matching `Makefile` target. Rejected: a Docker executor with the Pidgey image as a secondary container (needs a Pidgey image per branch; `build_backend` only builds at release), and a primary container carrying both PHP and Node (needs a new CI image). Accepted trade-off: a slower boot.

### Pack smoke test: adopted

- Runs on **every build**, in `clients_checks` right after the `npm pack --dry-run` file-list check. Rejected: only before publish (a broken tarball found only at release).
- `npm pack` both packages; in a fresh temporary project, `npm install --ignore-scripts --no-audit --no-fund ./pidgey-client-*.tgz ./pidgey-cli-*.tgz` (no registry access needed: zero runtime dependencies).
- Asserts: `import { PidgeyClient, PidgeyError } from 'pidgey-client'` resolves through the `exports` map; `npx pidgey --version` prints the CLI version and `npx pidgey --help` exits `0`; `require('pidgey-client')` works through `require(esm)` (dropped, with the CommonJS claim of `packaging.md`, if #226 picks a Node.js version without `require(esm)`). Not adopted: a deep-import assertion.
- The CLI is installed together with the packed library tarball. While the CLI's `package.json` declares `file:../nodejs`, its tarball does not resolve outside the repository: the smoke test packs the CLI with the dependency in its published form, which #222 defines.
- The publish gate itself belongs to #223.

### `.d.ts` type check: adopted

- Runs **inside the smoke test**, against the installed tarball, so a wrong `types` path in the `exports` map or a `.d.ts` missing from `files` fails. Rejected: checking the in-repo declarations.
- A TypeScript consumer fixture committed in `clients/nodejs/` (for example `specs/types/consumer.ts`), compiled with `tsc --noEmit` under `strict` and `module`/`moduleResolution: nodenext`, covering positive usage: every `PidgeyClient` option, `send` with a `signal`, narrowing `err instanceof PidgeyError` and reading `code`, `possiblySent`, `smtp`.
- Not adopted: `@ts-expect-error` negative cases (accepted gap: declarations degraded to `any` would still compile) and a CommonJS consumer fixture.
- **JSDoc check of the source:** `tsc --allowJs --checkJs --noEmit` on both packages (`yarn typecheck`) in `clients_checks`. The CLI gains `typescript` as a devDependency.

### CLI subprocess tests: adopted (thin layer)

- A spec in `clients/cli/specs/` (for example `specs/bin/Pidgey_spec.js`) spawns the real `bin/pidgey` against the in-process server; it runs inside the CLI's `yarn coverage`.
- Covers only what in-process specs cannot prove (real `bin` and shebang, process exit status, real pipes, signals, process lifetime). Parsing, token sources, body selection and the exit-code mapping stay in `Arguments_spec.js`, `Body_spec.js` and `ExitCode_spec.js`. Rejected: replacing `ExitCode_spec.js`; rejecting subprocess tests.
- Cases: exit `0` with empty stdout; exit `1` with the error on stderr; exit `2` on a usage error and on `CLIENT_CONFIGURATION`; a body piped through stdin reaching the server; a closed or empty stdin; the 10 MiB stdin cap through a real pipe (exit `2`, nothing sent); SIGINT while the request is in flight → exit `130`, connection aborted; **no-hang**: a `200` with keep-alive and the socket left open, the CLI must exit `0` within a bounded time (for example 5 s). This settles the "No-hang expectation" of `testing.md`.

### Coverage threshold: adopted

- Tool `c8` (settling `packaging.md`'s "nyc/c8"), `check-coverage: true`, **100 %** lines, branches, functions and statements for **both** packages. An unreachable branch takes `/* c8 ignore next */` with a reason. Rejected: 95 % for the CLI, a 90 % floor.
- Only `yarn coverage` counts (in-process specs plus the subprocess spec, which c8 follows through `NODE_V8_COVERAGE`); integration never counts.
- `clients_checks` uploads coverage to Codacy with `upload_codacy_coverage`, prefixes `clients/nodejs` and `clients/cli`; `coverage_final` also requires `clients_checks`.

### Node.js version matrix: adopted (floor + current)

- `clients_checks` is a CircleCI matrix over the **lowest** `engines` version and the `dockerfiles/vite_pidgey-base/` version (one job when equal); each job runs the full checks script. The numbers come from #226.
- The current-version job keeps `darthjee/circleci_node`; the floor uses a stock `cimg/node:<floor>` image.
- `clients_integration` and the Codacy upload run on the current version only.
- `make clients-checks` runs on the development version only (accepted gap: the floor is checked in CI only).
- Rejected: one job per supported major; deferring to #226; a single version.

### Commands, `Makefile` targets and CI jobs

- One script per job, shared by the `Makefile` and CI:
  - `clients/scripts/checks.sh`: per package `yarn install --frozen-lockfile`, `yarn lint`, `yarn typecheck`, `yarn coverage` (including the subprocess spec and the threshold), then the pack file-list check and the smoke test with the `.d.ts` check.
  - `clients/scripts/integration.sh`: the integration spec with the "required" flag set.
- `make clients-checks` runs `checks.sh` in a compose Node service; `make clients-integration` boots the compose stack and runs `integration.sh`; `make tests` runs backend + frontend + `clients-checks` + `clients-integration` (accepted: a slower local run).
- CircleCI: `clients_checks` runs in parallel with `backend_checks`, `backend_tests` and `frontend_checks`; `clients_integration` `requires: [clients_checks]`.
- Paths and names are indicative; the implementation may adjust them as long as the `Makefile` target and the CI job call the same script.
- **Ownership:** `clients/scripts/` is owned by `infra` (with the `Makefile`, `docker-compose.yml` and `.circleci/config.yml`); `nodejs` and `cli` own the npm scripts it calls.

### Edge-case rules

- The test server listens on `127.0.0.1`, port `0`, and clients target `127.0.0.1`, never `localhost` (IPv6-first resolution, port collisions in parallel runs).
- Teardown calls `server.closeAllConnections()` before `server.close()`, so an idle keep-alive socket never keeps Jasmine alive.
- The integration spec is skipped locally without a stack, but `clients_integration` sets a flag (for example `PIDGEY_INTEGRATION=required`) that turns a missing stack into a failure.
- Left to the implementation: a minimal env for spawned CLI processes, SIGINT timing, the smoke test's temporary directory location, Mailpit isolation and timing margins.

### Spec changes in this issue

- `docs/agents/specs/node-client/testing.md`: replace the #220 marker with the decisions above (new sections for the CI infrastructure, the extra checks, the edge-case rules), settle the "No-hang expectation" via the subprocess spec, add the subprocess spec to the CLI table, and update "CI and `make tests`" (scripts, targets, the matrix, `requires`, Codacy, `coverage_final`).
- `docs/agents/specs/node-client/packaging.md`: "nyc/c8" → `c8` (Stack); cross-reference the pack check (Supply chain) to the smoke test; add the `clients/scripts/` → `infra` row to Ownership.
- `docs/agents/specs/node-client.md`: remove #220 from the pending table; update the `testing.md` row of the Files table if needed.

### Out of scope

- The Node.js version and `engines` range (#226); the CLI-to-library dependency form (#222); the publish gate and release procedure (#223).
- Implementation of the checks, scripts, targets and jobs (later sub-issues of #219).
- Reopening what the epic already decided (in-process server with real `fetch`, the Mailpit delivery assertions).
- The frontend's coverage policy and the backend tests.

## Benefits

- The implementation sub-issues get a fixed, complete test contract for both packages.
- The shipped tarball, its exports map, its `bin` and its declarations are verified on every build, not discovered broken at release.
- The CLI's process-level behavior (exit codes, signals, the no-hang rule) is tested for real.
- `make tests` and CI run the same scripts, and the `engines` floor is proven in CI rather than only declared.
