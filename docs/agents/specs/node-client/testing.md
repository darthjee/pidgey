# Testing

Spec of how `pidgey-client` and `pidgey-cli` are tested and which CI jobs run the tests.

## Approach

- **Real `fetch`, local server.** Library specs exercise HTTP with real `fetch` against an
  in-process `node:http` server started per spec, not a stubbed `globalThis.fetch`: only a
  real server proves that a redirect is not followed (`redirect: 'manual'`), timeouts, non-JSON bodies and abort wiring.
  Stubbing `fetch` cannot prove those behaviours.
- **The receiver is the spec itself: no mock container.** The server listens on `127.0.0.1`
  with port `0`, records every request (method, path, headers, body) for the spec to assert
  on, and is scripted per spec for the reply: envelope errors, delays, a reset after the
  request is written, a non-JSON or oversized body, an idle keep-alive socket. It runs in
  `clients_checks` on a single `darthjee/circleci_node` container, like `frontend_checks`,
  with no secondary container. Rejected: a mock server image (WireMock, MockServer, an echo
  server) as a secondary container, since it cannot easily script those transport failures,
  makes the specs slower and flakier, and adds an image to maintain.
- **Fast backoff.** Backoff is tested with a tiny `retryDelayMs` plus a spy on the delay
  function, so specs stay fast without fake timers.
- **End-to-end in CI.** An end-to-end run is part of CI, not opt-in
  ([Integration](#integration)). Mailpit already exists in `docker-compose.yml`, so a real
  delivery assertion is cheap.
- Specs mirror the source tree of each package.

### Edge-case rules

- The test server listens on `127.0.0.1`, port `0`, and the clients under test target
  `127.0.0.1`, never `localhost`: `localhost` resolves IPv6-first, and a fixed port collides
  in parallel runs.
- Teardown calls `server.closeAllConnections()` before `server.close()`, so an idle
  keep-alive socket never keeps Jasmine alive.
- The integration spec is skipped locally without a stack, but `clients_integration` sets a
  flag (for example `PIDGEY_INTEGRATION=required`) that turns a missing stack into a failure
  instead of a skip ([Integration](#integration)).
- Left to the implementation's judgment: a minimal env for spawned CLI processes, SIGINT
  timing, the location of the smoke test's temporary directory, Mailpit isolation and timing
  margins.

## Library (`clients/nodejs/specs/`)

| Spec | Covers |
| --- | --- |
| `specs/lib/config_spec.js` | Configuration validation and defaults ([library.md](library.md#new-pidgeyclientoptions)): `token`, `baseUrl` (scheme, trailing slash, path prefix), `maxRetries`, `retryDelayMs` (non-negative integer), `timeoutMs` (positive integer), `retryAmbiguous` and `allowInsecureHttp` (booleans), `undefined` taking the default; the cleartext rule ([library.md](library.md#cleartext-transport)): the loopback forms accepted without opt-in (`localhost`, `LOCALHOST`, `127.0.0.1`, `127.1`, `[::1]`), the non-exempt forms rejected without opt-in (`localhost.`, `*.localhost`, `[::ffff:127.0.0.1]`, `0.0.0.0`, any other host), `allowInsecureHttp: true` accepting them, the option ignored with `https://`, and the `CLIENT_CONFIGURATION` message never containing the token or the URL userinfo (`http://user:pass@host`). |
| `specs/lib/index_spec.js` | The public exports (`PidgeyClient`, `PidgeyError`, `VERSION`) and nothing else. |
| `specs/lib/PidgeyClient_spec.js` | The constructor throwing `CLIENT_CONFIGURATION` synchronously; the client exposing no own property and not leaking the token through `util.inspect` or `JSON.stringify`; the request over the real server: method, path (with a path prefix kept), the header set (including an `Idempotency-Key` that is a new `crypto.randomUUID()` per call, or the caller's key as is), no `Origin` header, the whitelisted fields as the JSON body, `undefined` fields omitted, an idle keep-alive connection that does not hang the suite. |
| `specs/lib/PidgeyClientSend_spec.js` | The resolved `{ status, smtp }` passthrough (a field added by the API, a `200` body without `smtp`, a gzip body); `replayed` mapped from `Idempotent-Replayed: true` (`false` for another value or none, and not overridable by the body); invalid `send` arguments (a message that is not an object, an invalid `idempotencyKey`, a `signal` that is not an `AbortSignal`) rejected with `CLIENT_CONFIGURATION`, no request made and the key never echoed; rejection and never a synchronous throw; the API failures (envelope mapping, validation `details`, `smtp` with `possiblySent` on `SMTP_TIMEOUT`, a reflected token redacted, a non-JSON error body or a non-JSON `200`, a body over 64 KiB on a `200` and on an error, a 3xx not followed with its status kept); the network failures (refused connection unambiguous; destroyed socket, timeout and timeout while reading the body ambiguous; the token and URL never in the message). |
| `specs/lib/PidgeyClientSendAbort_spec.js` | `ABORTED` from a caller signal: already aborted (`possiblySent: false`, no request), in flight and while reading the body of a `400` (`possiblySent: true`), the caller's abort winning over the timeout, a signal that never fires; two concurrent `send` calls independent, also when one fails. |
| `specs/lib/request_spec.js` | The request helpers: the idempotency key format (1 to 255 characters in `0x21`-`0x7E`), the body whitelist (`undefined` omitted, values not validated, a non-object or non-serializable message rejected), the network classification by `cause` code (each unambiguous code, the TLS patterns, ambiguous otherwise, nested and cyclic cause chains, a non-string code, a thrown non-object), and the over-cap `INVALID_RESPONSE` (`possiblySent` on a 2xx only). |
| `specs/lib/response_spec.js` | The capped body read ([errors-and-retry.md](errors-and-retry.md#response-body)): the 64 KiB cap (by bytes after decompression, and by `Content-Length` without reading), UTF-8 decoding with replacement and a character split across chunks, an aborted signal; token redaction (every occurrence, the trimmed and configured forms, longer first, the token not treated as a pattern); the 4 KiB cut without splitting a multi-byte or astral character. |
| `specs/lib/response_map_spec.js` | The mapping of a response to a result or a `PidgeyError`: the success passthrough; the envelope to `statusCode`/`code`/`message`/`details`/`smtp` with the fallback to the HTTP status; `possiblySent` per code and status; `INVALID_RESPONSE` for a non-envelope body, a 1xx, any 3xx (even with an envelope) and a non-object 2xx body; the redaction of the fields copied out of the envelope; the retained `body` (cut, `bodyTruncated`, the token straddling the 4 KiB boundary redacted, no split character). |
| `specs/lib/PidgeyError_spec.js` | The class: `name`, `message`, `code`, `possiblySent` (default `false`); the optional fields absent when not given; `body` and `bodyTruncated` readable, read-only, non-enumerable and absent from `JSON.stringify` and `util.inspect`. |
| `specs/lib/retry_spec.js` | The pure retry helpers: the decision for every code and status of the [retry table](errors-and-retry.md#retry-table), with `retryAmbiguous` off and on (an ambiguous attempt retried only with the option, the never-retried codes refused with it too); the backoff delay (`retryDelayMs` doubling per retry, capped at `8000` ms, also for a base above `8000`); the abortable wait (resolves after the delay, rejects at once on a signal, also an already aborted one, and clears its timer). |
| `specs/lib/RetryPolicy_spec.js` | One case per row of the [retry table](errors-and-retry.md#retry-table) (each `SMTP_*` code, unknown `SMTP_*` on `502`/`503`/`504`, network errors); no retry on `400`/`401`/`403`/`409`/`422`/`429`/`500`, 3xx and a non-JSON `200`; `ABORTED` from a caller signal during an attempt and during the backoff wait; attempt count for `maxRetries` `0`, `1` and `2`; backoff growing and capped; the last error surfacing when retries are exhausted; a timeout classified as an ambiguous `NETWORK_ERROR`; `retryAmbiguous` off and on; `possiblySent` for every code, including the "any attempt was ambiguous" rule; `possiblySent` for `ABORTED`: before any request, in flight (including while the body is being read), during the backoff after unambiguous failures, after an earlier ambiguous attempt, and an abort while reading the body of an already-received `400`/`401`/`403`/`409` (`true`); one idempotency key reused across the retries of a call and a new one for the next call; `409 IDEMPOTENCY_KEY_IN_USE` never retried with `possiblySent: true` and `422 IDEMPOTENCY_KEY_REUSED` never retried with `possiblySent: false`, also with `retryAmbiguous: true`. |
| `specs/lib/Version_spec.js` | The `VERSION` export ([library.md](library.md#exports)) equals the `version` in the library's `package.json`; there is no build step to inject it, so this spec keeps the two from drifting. |
| `specs/contract/OpenApi_spec.js` | Request bodies built by `send` and the response fixtures used by the specs validate against the OpenAPI document; the generated `types/` file is up to date with it ([openapi.md](openapi.md#contract-tests)). |

The scripted server is the helper `specs/support/TestServer.js` (not a spec): an in-process
`node:http` server on `127.0.0.1`, port `0`, that records each request and answers from a
script (JSON or raw bodies, gzip, a delay, a destroyed socket, a stalled body).

## CLI (`clients/cli/specs/`)

The scripted server of the subprocess spec is the helper `specs/support/TestServer.js` (not a
spec): an in-process `node:http` server on `127.0.0.1`, port `0`, that records each request
and answers from a script (a status and JSON body, or a held answer released later), leaving the
keep-alive socket open.

| Spec | Covers |
| --- | --- |
| `specs/cli/Arguments_spec.js` | Parsing of every flag; `--help` and `--version` (with `--help` winning); `--token` rejected as an unknown flag; unknown, repeated and positional arguments and a missing `--to` or `--subject` as parse errors (the usage is asked for); `--idempotency-key`, `--retry-ambiguous`, `--allow-insecure-http` and `--verbose` parsed. |
| `specs/cli/Credentials_spec.js` | `--token-file`/`--url` versus `PIDGEY_TOKEN`/`PIDGEY_URL` precedence; missing token or URL → exit `2`; one trailing newline (`\n` or `\r\n`) trimmed from the token file; missing, unreadable (a symlink loop) and empty token file → exit `2`; a token file over 4 KiB → exit `2`, exactly 4 KiB accepted; a non-regular file (`/dev/stdin`, a FIFO, a directory; a symlink to a regular file is accepted) → exit `2`; a control character (CR, LF, NUL, tab) left after the trim → exit `2`, with a message naming the path only; the group/other-readable token file warning, taken from `fstat` of the descriptor read, and none on Windows. |
| `specs/cli/Body_spec.js` | Stdin read when neither body flag is given and stdin is not a TTY; no stdin read when `--html` is present; unreadable stdin; the stdin cap ([cli.md](cli.md#stdin-size-cap)): exactly 1 MiB accepted, 1 MiB + 1 byte → exit `2` with a message naming the limit and nothing sent, reading stopped at the cap, and the cap counted in bytes before decoding; SIGINT while stdin is being read stops the read and sends nothing. |
| `specs/cli/ExitCode_spec.js` | `0` on a sent message, `1` on a `PidgeyError`, `2` on a usage error and on `CLIENT_CONFIGURATION`, `130` on SIGINT; the output: nothing on success, the one-line `--verbose` result, the failure lines (code and message, the SMTP reply, the possibly-sent notice), and never the token, the message, the URL userinfo or the response body. |
| `specs/cli/run_spec.js` | The command in-process, with an injected `io` and client: `--version` and `--help`; the usage after a parse error only; the client built from the credentials and the flags; the message parts, the signal and the idempotency key passed to `send()`; stdin as the text body; a real client built by default. |
| `specs/cli/version_spec.js` | The CLI `VERSION` equals the `version` in `package.json`. |
| `specs/bin/Pidgey_spec.js` | The real `bin/pidgey` spawned against the in-process server ([Extra checks](#cli-subprocess-tests-adopted-thin-layer)), covering only what in-process specs cannot prove: exit `0` with empty stdout; exit `1` with the error on stderr; exit `2` on a usage error and on `CLIENT_CONFIGURATION`; a body piped through stdin reaching the server; a closed or empty stdin; the 1 MiB stdin cap through a real pipe (exit `2`, nothing sent); SIGINT while the request is in flight → exit `130` with the connection aborted; and the [no-hang case](#no-hang-expectation). Parsing, token sources, body selection, the output and the exit-code mapping stay in the in-process specs above. |

## Cross-cutting

- **Backend, OpenAPI diff:** the `/v1` breaking-change diff against `main` (the `OpenAPI
  breaking-change check` step of `backend_checks`, `scripts/check_openapi_breaking.sh`) runs in the
  backend checks and is green ([openapi.md](openapi.md#v1-is-additive-only)).
- **Backend, OpenAPI route:** a PHPUnit test for `GET /v1/openapi.json` (`200`, JSON content
  type, no token required, valid OpenAPI 3.1); the
  [content rules](openapi.md#content-rules) (every path starts with `/v1`, no `servers`
  entry with a real host, `info.version` is not the Pidgey release version); no cache
  headers; an invalid Bearer is still served; and a check that the documented status codes
  of `POST /v1/messages` match the document.
- **Contract:** `specs/contract/OpenApi_spec.js` in the library (above).
- **Pack check:** `npm pack --dry-run` in both packages, compared against an explicit
  expected file list committed per package; any added or missing file fails
  ([packaging.md](packaging.md#supply-chain)).
- **Committed-file lint:** `checks.sh` fails if the committed `clients/cli/package.json`
  declares `pidgey-client` as anything other than `file:../nodejs`
  ([packaging.md](packaging.md#supply-chain)).
- **Lint:** `yarn lint` in both packages, with the same rules as `frontend/`, green.

## No-hang expectation

The CLI exits promptly after a send against a keep-alive server, without waiting on an idle
keep-alive socket ([library.md](library.md#connections)). This is tested by the CLI
subprocess spec (`specs/bin/Pidgey_spec.js`, [Extra checks](#cli-subprocess-tests-adopted-thin-layer)):
the in-process server answers `200` with keep-alive and leaves the socket open, and the CLI
must exit `0` within a bounded time (for example 5 s).

## Integration

`specs/integration/Send_spec.js`, run by the dedicated CI job `clients_integration` and a
matching `Makefile` target:

1. The job runs on a CircleCI **machine executor** and boots the stack with
   `docker compose`: Pidgey backend, MySQL, Mailpit. It is the same stack and the same
   commands as the matching `Makefile` target, `make clients-integration`.
2. It seeds an organization, a `send`-scoped API token and an SMTP connection pointing at
   Mailpit.
3. It sends through the library **and** through the `pidgey` CLI, and asserts delivery
   through Mailpit's HTTP API (`GET /api/v1/messages`): a real Pidgey delivers over SMTP to
   Mailpit, and Mailpit lists.
4. It also covers a real `409 NO_SMTP_CONNECTION` for an organization without an active
   connection, and a real `401` for a revoked token.

Locally the spec is skipped unless the environment points at a running stack. The exception
is CI: `clients_integration` sets the "required" flag (for example
`PIDGEY_INTEGRATION=required`), which turns a missing stack into a failure instead of a skip.

**Rejected executors:**

- A Docker executor with the Pidgey image as a secondary container: it needs a Pidgey image
  per branch, and `build_backend` only builds at release.
- A primary container carrying both PHP and Node: it needs a new CI image.

Accepted trade-off: the machine executor boots slower.

## CI and `make tests`

One script per job, shared by the `Makefile` and CI (paths and names are indicative: the
implementation may adjust them as long as the `Makefile` target and the CI job call the same
script):

| Script | Runs |
| --- | --- |
| `clients/scripts/checks.sh` | Per package: `yarn install --frozen-lockfile`, `yarn lint`, `yarn typecheck`, `yarn coverage` (including the CLI subprocess spec and the 100 % threshold); then the committed-file lint of the CLI's `pidgey-client` dependency, the pack file-list check and the [smoke test](#pack-smoke-test-adopted) (CLI packed through the staging-copy script, range assertion, offline install) with the `.d.ts` check. |
| `clients/scripts/integration.sh` | The [integration](#integration) spec, with the "required" flag set. |

| Job | Runs |
| --- | --- |
| `clients_checks` | `checks.sh`, as a CircleCI matrix over the [Node.js floor and current versions](#nodejs-version-matrix-adopted-floor--current), in parallel with the existing `backend_checks`, `backend_tests` and `frontend_checks` jobs. On the current version it uploads the coverage of each package to Codacy with `upload_codacy_coverage`, with the prefixes `clients/nodejs` and `clients/cli`. |
| `clients_integration` | `integration.sh`: the [integration](#integration) spec against Pidgey + MySQL + Mailpit, on a machine executor, on the current Node.js version only. `requires: [clients_checks]`. |

- `clients_checks` ships with the library scaffold, so `clients/` is checked from its first
  commit: [contributing.md](../../contributing.md) requires the check job of a new
  application folder to exist before changes to that folder are merged.
- `coverage_final` also requires `clients_checks`.
- The `Makefile` gains the client targets, so `make tests` keeps being the single command
  that runs every check:
  - `make clients-checks` runs `checks.sh` in a compose Node service;
  - `make clients-integration` boots the compose stack (Pidgey backend, MySQL, Mailpit) and
    runs `integration.sh`;
  - `make tests` runs backend + frontend + `clients-checks` + `clients-integration`.
    Accepted: a slower local run.
- The `Makefile` target and the CI job call the **same** script: `clients_checks` must not
  become a job that `make tests` skips. The one exception is the Node.js matrix:
  `make clients-checks` runs on the development version only, and the floor is checked in CI
  only.
- Done means: `clients_checks` and `clients_integration` green on a branch that touches
  `clients/`, and `make tests` green locally.

## Extra checks

Every check below runs in `clients_checks` through the shared
`clients/scripts/checks.sh` script, which `make clients-checks` also calls
([CI and `make tests`](#ci-and-make-tests)). Script paths, fixture and spec file names are
indicative: the rule is that the `Makefile` target and the CI job call the same script.

### Pack smoke test: adopted

- **Package and job:** both packages, in `clients_checks`, on **every build**, as a step of
  `checks.sh` right after the `npm pack --dry-run` file-list check
  ([Pack check](#cross-cutting)). Rejected: running only before publish, which would find a
  broken tarball only at release.
- **Command:** `npm pack` the library, and pack the CLI through the staging-copy script
  ([packaging.md](packaging.md#cli-to-library-dependency)); in a fresh temporary project,
  `npm install --offline --ignore-scripts --no-audit --no-fund ./pidgey-client-*.tgz ./pidgey-cli-*.tgz`.
  The install is offline: the library has no runtime dependencies, and the packed CLI's
  `^<floor>` range is satisfied by the packed library tarball, so nothing is fetched and
  `pidgey-client` need not be on the registry yet.
- **Asserts:**
  - the `package.json` inside the packed CLI tarball declares
    `dependencies["pidgey-client"]` as exactly `^<version in clients/nodejs/package.json>`;
  - `import { PidgeyClient, PidgeyError } from 'pidgey-client'` resolves through the
    `exports` map;
  - `npx pidgey --version` prints `pidgey-cli <version> (pidgey-client <version>)`, the CLI
    version matching its `package.json` and the library version matching the packed library
    tarball; `npx pidgey --help` exits `0`;
  - `require('pidgey-client')` works through `require(esm)`.
- **Not adopted:** a deep-import assertion.
- **CLI tarball:** the CLI is installed together with the packed library tarball. The
  committed `clients/cli/package.json` declares `file:../nodejs`, whose tarball would not
  resolve outside the repository, so the smoke test packs the CLI through the staging-copy
  script, the same script the publish uses: the tarball carries the published `^<floor>`
  form and the working tree is never modified.
- **Registry check not run here:** `npm view pidgey-client@<floor>` runs at publish time
  only, so the smoke test works before the first publish.
- **Publish gate:** specified in
  [packaging.md](packaging.md#pre-publish-gate). The publish job re-uses the tarball the pack
  job built and checked; it never re-packs.

### `.d.ts` type check: adopted

- **Inside the smoke test**, against the **installed tarball**, so a wrong `types` path in
  the `exports` map or a `.d.ts` missing from `files` fails. Rejected: checking the in-repo
  declarations.
- **Fixture:** a TypeScript consumer fixture committed in `clients/nodejs/` (for example
  `specs/types/consumer.ts`), compiled with `tsc --noEmit` under `strict` and
  `module`/`moduleResolution: nodenext`. It covers positive usage only: every
  `PidgeyClient` option, `send` with a `signal`, and narrowing `err instanceof PidgeyError`
  and reading `code`, `possiblySent` and `smtp`.
- **Not adopted:** `@ts-expect-error` negative cases, and a CommonJS consumer fixture.
  Accepted gap: declarations degraded to `any` would still compile.

### JSDoc check of the source: adopted

`tsc --allowJs --checkJs --noEmit` on both packages, through `yarn typecheck`, in
`clients_checks`. The CLI gains `typescript` as a devDependency.

### CLI subprocess tests: adopted (thin layer)

- **Spec:** one spec in `clients/cli/specs/` (for example `specs/bin/Pidgey_spec.js`) spawns
  the real `bin/pidgey` against the in-process server of [Approach](#approach). It runs
  inside the CLI's `yarn coverage`, in `clients_checks`.
- **Scope:** only what in-process specs cannot prove: the real `bin` and shebang, the
  process exit status, real pipes, signals and the process lifetime. Parsing, token
  sources, body selection and the exit-code mapping stay in `Arguments_spec.js`,
  `Credentials_spec.js`, `Body_spec.js` and `ExitCode_spec.js`. Rejected: replacing `ExitCode_spec.js` with
  subprocess tests; rejecting subprocess tests altogether.
- **Cases:** see its row in the [CLI table](#cli-clientsclispecs). The **no-hang** case
  settles the [No-hang expectation](#no-hang-expectation).

### Coverage threshold: adopted

- **Tool:** `c8` (settling the "nyc/c8" of [packaging.md](packaging.md)), with
  `check-coverage: true` and **100 %** lines, branches, functions and statements for
  **both** packages. A branch that cannot be reached takes `/* c8 ignore next */` with a
  reason. Rejected: 95 % for the CLI, a 90 % floor.
- **What counts:** only `yarn coverage`, that is, the in-process specs plus the subprocess
  spec, which c8 follows through `NODE_V8_COVERAGE`. The integration spec never counts.
  Merging integration coverage is rejected.
- **Upload:** see [CI and `make tests`](#ci-and-make-tests).

### Node.js version matrix: adopted (floor + current)

- `clients_checks` is a CircleCI matrix over the **lowest** `engines` version and the
  version of `dockerfiles/vite_pidgey-base/`; it is one job when they are equal. Each job
  runs the full `checks.sh`. The jobs: floor on `cimg/node:22.12.0` (Node.js 22.12.0) and
  current on `darthjee/circleci_node:0.2.1` (Node.js 22.14.0); see
  [packaging.md](packaging.md#nodejs-version). No latest-LTS job is added.
- The current-version job keeps `darthjee/circleci_node`; the floor job uses a stock
  `cimg/node:<floor>` image.
- `clients_integration` and the Codacy coverage upload run on the current version only.
- `make clients-checks` runs on the development version only. Accepted gap: the floor is
  checked in CI only.
- Rejected: one job per supported major (so no latest-LTS job); deferring the matrix; a single version.
