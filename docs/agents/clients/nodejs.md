# Node.js client (`pidgey-client`)

The library for Pidgey's `/v1` API, in `clients/nodejs/`. It has the working **core**: the
configuration, `send` with its retry policy, `PidgeyError` and the failure classification, its
types generated from the `/v1` OpenAPI document and its contract spec (#250). The CLI that
consumes it is in [cli.md](cli.md). The rules are specified in
[specs/node-client/library.md](../specs/node-client/library.md) and
[specs/node-client/errors-and-retry.md](../specs/node-client/errors-and-retry.md); this page
records what was built and links the rules instead of copying them.

## Package

| Field | Value |
| --- | --- |
| Name | `pidgey-client` |
| Version | `0.1.0`, the first release line; nothing is published before the epic's release. |
| Module system | ESM (`"type": "module"`). |
| Entry point | `exports` maps `.` to `{ "types": "./dist/lib/index.d.ts", "default": "./lib/index.js" }`: the runtime is `lib/index.js` and the declarations are in `dist/`, see [Types](#types). |
| Node.js | `engines.node` is `>=22.12.0`. |
| Published files | The `files` allowlist holds `lib/` (eight modules) and `dist/` (the generated declarations); npm adds `package.json` and `README.md`. |
| Dependencies | No runtime dependency. Why: the library stays dependency-free to keep its supply chain empty. The type generator and the contract-spec validators are devDependencies, pinned to exact versions (see [Types](#types)). |
| Install scripts | None (`preinstall`, `install`, `postinstall`, `prepare`). Why: nothing runs at the consumer's install. |
| License | MIT. |

`yarn.lock` is committed and the install runs with `--frozen-lockfile`, so a stale lockfile
fails the checks.

## Surface

`lib/index.js` exports `PidgeyClient`, `PidgeyError` and `VERSION`, and nothing else
([library.md](../specs/node-client/library.md#exports)). `VERSION` is a string literal
(`'0.1.0'`) that `specs/lib/Version_spec.js` keeps equal to `package.json`.

### Modules

| File | Role |
| --- | --- |
| `lib/index.js` | The public exports. |
| `lib/PidgeyClient.js` | The client: the `send` call, its retry loop and one attempt. Its configuration is in private fields, so the token never shows in `console.log(client)`, `JSON.stringify(client)` or a logger. It keeps no per-call state, so concurrent sends are independent. |
| `lib/PidgeyError.js` | The error class. |
| `lib/config.js` | Option validation, the defaults and the cleartext rule. |
| `lib/retry.js` | The retry policy helpers, internal and not exported: the retry decision, the backoff delay and the abortable wait. |
| `lib/request.js` | The request body whitelist, the idempotency key check and the classification of a `fetch` failure. |
| `lib/response.js` | The capped body read, the redaction, the cut and the mapping of a response to a result or an error. |
| `lib/version.js` | `VERSION`. |

### Options

`new PidgeyClient(options)` validates synchronously and throws `PidgeyError`
`CLIENT_CONFIGURATION`, before any request. `undefined` means "use the default". The table
sums up what was built; the rules are in
[library.md](../specs/node-client/library.md#new-pidgeyclientoptions).

| Option | Default | Valid |
| --- | --- | --- |
| `token` | required | A string with a non-whitespace character. |
| `baseUrl` | required | Parses as an absolute URL with the `http:` or `https:` scheme. The endpoint is `<baseUrl>/v1/messages`: a trailing slash is dropped and a path prefix is kept. |
| `allowInsecureHttp` | `false` | A boolean. |
| `maxRetries` | `2` | A non-negative integer. At most `maxRetries + 1` attempts per `send`; `0` means exactly one. |
| `retryDelayMs` | `500` | A non-negative integer. The base of the backoff; every wait is capped at 8000 ms, so a larger value waits 8000 ms. |
| `retryAmbiguous` | `false` | A boolean. When `true`, an attempt that may have delivered the message is retried too, at the risk of a duplicate. |
| `timeoutMs` | `30000` | A positive integer. |

The error messages never include the token, the URL or the key.

**Cleartext rule.** An `http:` URL on a non-loopback host needs `allowInsecureHttp: true`, because
the Bearer token would travel in clear text. The check runs on the hostname as normalized by URL
parsing, with no DNS lookup: `localhost`, any `127.x.x.x` address and `[::1]` are exempt;
`localhost.`, `*.localhost`, `[::ffff:7f00:1]` and `0.0.0.0` are not. With `https:` the option
has no effect ([library.md](../specs/node-client/library.md#cleartext-transport)).

### `send(message, { signal, idempotencyKey })`

- **Arguments.** A mistake rejects with `CLIENT_CONFIGURATION` before any request: a message that
  is not a plain object, a message that cannot be serialized as JSON, an `idempotencyKey` outside
  `/^[\x21-\x7E]{1,255}$/`, or a `signal` that is not an `AbortSignal`. A signal that is already
  aborted rejects with `ABORTED` and `possiblySent: false`.
- **Request.** `POST <endpoint>` with `Authorization: Bearer <token>`, `Content-Type` and
  `Accept` as `application/json`, no `Origin`, and an `Idempotency-Key` always sent: the
  caller's key, or a `crypto.randomUUID()` generated once per `send` call. The body holds only
  `to`, `subject`, `text` and `html`, with `undefined` fields omitted and no value validated:
  the API is the only validator
  ([library.md](../specs/node-client/library.md#message)).
- **Success.** On a 2xx with a JSON object body, the promise resolves with that body unchanged
  plus `replayed`, `true` only when the response carries `Idempotent-Replayed: true`
  ([library.md](../specs/node-client/library.md#success)).
- **Redirects.** `fetch` runs with `redirect: 'manual'`. A 3xx is never followed, so the `POST`
  is not replayed as a `GET` and the token goes to no other host. It rejects with
  `INVALID_RESPONSE`, keeping its `statusCode`, with `possiblySent: false`. `redirect: 'error'`
  was not used because it surfaces the redirect as a network error without a status.
- **Retries.** See [Retry policy](#retry-policy).
- **Abort and timeout.** The caller's signal and a per-attempt timer (`timeoutMs`) are combined
  with `AbortSignal.any`, and the timer is cleared when the attempt ends. See
  [Failure classification](#failure-classification).

### Retry policy

`send` runs the attempt in a loop of at most `maxRetries + 1` attempts. Every attempt sends the
same body and the same `Idempotency-Key`; the next `send` call gets a new key. The rules are in
[errors-and-retry.md](../specs/node-client/errors-and-retry.md#retry-policy).

**Decision.** `lib/retry.js` decides from the error's `code`, `statusCode` and `possiblySent`,
never from the raw `smtp` reply, in this order:

1. Never retried, whatever the options: `INVALID_RESPONSE`, `ABORTED`, `CLIENT_CONFIGURATION`,
   `SMTP_PROTOCOL_ERROR`, `IDEMPOTENCY_KEY_IN_USE` and `IDEMPOTENCY_KEY_REUSED`.
2. An ambiguous attempt (`possiblySent: true`) is retried only when `retryAmbiguous` is on. This
   is what keeps the default client from ever causing a duplicate.
3. `NETWORK_ERROR`, `SMTP_CONNECTION_FAILED` and `SMTP_TEMPORARY_FAILURE` are retried.
4. `SMTP_TIMEOUT` is retried; it is always ambiguous, so step 2 already needs `retryAmbiguous`.
5. An unknown `SMTP_*` code (one outside the seven the specs name: `SMTP_CONNECTION_FAILED`,
   `SMTP_TEMPORARY_FAILURE`, `SMTP_TIMEOUT`, `SMTP_REJECTED`, `SMTP_AUTH_FAILED`,
   `SMTP_TLS_FAILED` and `SMTP_PROTOCOL_ERROR`) is retried on a `503` or a `504` (the `504` is
   ambiguous, so step 2 gates it); a `502` or any other status is not. A known code that is not
   in steps 3 and 4, such as `SMTP_REJECTED`, falls to step 6.
6. Anything else is not retried.

**Backoff.** Before retry *n* (1-based) the loop waits `min(retryDelayMs * 2^(n-1), 8000)` ms, so
the defaults wait 500, 1000, 2000... ms. The cap applies to the first wait too: a `retryDelayMs`
of `10000` waits 8000 ms. The wait is a timer the caller's signal cancels, and a signal that is
already aborted rejects at once. No jitter is added and no `Retry-After` is read (`/v1` sends
none).

**Outcome.**

- When a failure is not retried, or the retries run out, `send` rejects with the **last** error.
  Its `possiblySent` is `true` if **any** attempt was ambiguous.
- A caller abort during the backoff rejects with a new `ABORTED` error, with no further attempt.
  Its `possiblySent` is `true` only when an earlier attempt was ambiguous.
- The per-attempt timeout restarts with each attempt; a timeout is an ambiguous `NETWORK_ERROR`,
  so it is retried only with `retryAmbiguous`.

### `PidgeyError`

`extends Error`, with `name = 'PidgeyError'`. Fields:

| Field | Notes |
| --- | --- |
| `code`, `message`, `possiblySent` | Always present and enumerable. `possiblySent` is a boolean that defaults to `false`. |
| `statusCode`, `details`, `smtp` | Enumerable and **absent** (not `undefined`) when there is none. `statusCode` is the envelope's, else the HTTP status. |
| `body`, `bodyTruncated` | **Non-enumerable**, read-only, set only when a response text was read, so `JSON.stringify`, `util.inspect` and enumerating loggers do not print them. |

The codes, the fields and the response body rules are in
[errors-and-retry.md](../specs/node-client/errors-and-retry.md#pidgeyerror).

**Response body.** Every response is read up to 64 KiB of bytes after decompression: a
`Content-Length` above the cap is refused without reading, and a longer stream cancels the read.
Invalid UTF-8 becomes replacement characters. The token is redacted from the whole text with
`[redacted]` **before** the cut and before the JSON parse, so it is absent from `message`,
`details`, `smtp` and `body`. Both the configured token and its trimmed form (`fetch` strips
header whitespace) are matched, the longer first; encoded forms are not. `body` keeps the
redacted text cut to 4 KiB of UTF-8 without splitting a character, and `bodyTruncated` says
whether it was cut.

### Failure classification

| Outcome | Code | `possiblySent` |
| --- | --- | --- |
| Envelope `{ error: { code, message } }` on a non-2xx | The envelope's `code` | `true` for `SMTP_TIMEOUT`, `SMTP_PROTOCOL_ERROR`, `IDEMPOTENCY_KEY_IN_USE` and any `SMTP_*` on a `504`; `false` otherwise (including `IDEMPOTENCY_KEY_REUSED`). |
| Non-2xx without the envelope (not JSON, or no `error.code` and `error.message`) | `INVALID_RESPONSE`, keeping the HTTP status | `false` |
| Any 3xx | `INVALID_RESPONSE`, keeping the HTTP status | `false` |
| 2xx whose body is not a JSON object | `INVALID_RESPONSE` | `true` |
| Body over 64 KiB, on a 2xx | `INVALID_RESPONSE` | `true` |
| Body over 64 KiB, on any other status | `INVALID_RESPONSE` | `false` |
| The caller's signal fires during `fetch` or the body read | `ABORTED` | `true` (the status is not consulted) |
| The per-attempt timeout fires | `NETWORK_ERROR` | `true` |
| Any other `fetch` or read failure | `NETWORK_ERROR` | `true`, unless the cause proves nothing was sent |

A caller abort wins over the timeout when both fired. **`NETWORK_ERROR` cause-code rule.**
`fetch` does not say whether the request was written, so the first string `code` along the
`cause` chain (at most 8 levels) decides. `possiblySent` is `false` only for `ECONNREFUSED`,
`ENOTFOUND`, `EAI_AGAIN`, `EHOSTUNREACH`, `ENETUNREACH`, `DEPTH_ZERO_SELF_SIGNED_CERT`,
`UNABLE_TO_VERIFY_LEAF_SIGNATURE` and the TLS or certificate codes (`ERR_TLS_*`, `ERR_SSL_*`,
`CERT_*`, `*_CERT_*`). Everything else, such as `ECONNRESET`, `UND_ERR_SOCKET` or a failure
with no code, is ambiguous. This errs on the safe side
([errors-and-retry.md](../specs/node-client/errors-and-retry.md#ambiguous-failures)).

### Specs layout

The specs mirror `lib/` under `clients/nodejs/specs/lib/`, plus shared helpers and the contract:

- `config_spec.js`, `request_spec.js`, `retry_spec.js` (the decision, the backoff delay and the
  abortable wait), `response_spec.js` (the body read, redaction and cut),
  `response_map_spec.js` (the mapping of a response) and `Version_spec.js`;
- `index_spec.js`, `PidgeyClient_spec.js`, `PidgeyClientSend_spec.js`,
  `PidgeyClientSendAbort_spec.js`, `RetryPolicy_spec.js` (the retry loop end to end against
  `TestServer`) and `PidgeyError_spec.js`;
- `specs/contract/OpenApi_spec.js` and `specs/support/Fixtures.js`, see
  [Contract spec](#contract-spec), and `specs/types/consumer.ts`, see
  [Consumer fixture](#consumer-fixture);
- `specs/integration/Send_spec.js`, the end-to-end spec against a real Pidgey, run by
  `yarn test:integration` only ([clients.md](../clients.md#integration));
- `specs/support/TestServer.js`, a scripted in-process `node:http` server on `127.0.0.1` with
  port `0`: it records the requests and answers from a script (JSON or raw bodies, gzip,
  delays, a destroyed socket, a stalled body). The specs use real `fetch` against it.

The coverage threshold is 100 %, over every file of `lib/`.

## Types

The library ships TypeScript declarations for its public surface. The schema types come from
the `/v1` OpenAPI document, the single source of truth, instead of being written by hand: the
declarations cannot drift from the API the document describes. The library itself stays plain
JavaScript with JSDoc, and keeps zero runtime dependencies.

**Generated schema types.** `clients/nodejs/types/v1.ts` is generated from
`source/openapi/v1.json` and **committed**. `yarn generate:types` rewrites it with
`openapi-typescript`. Rules:

- a change to the `/v1` document (the contract is additive-only, see
  [openapi.md](../specs/node-client/openapi.md#v1-is-additive-only)) needs a regeneration, in
  the same change set: run `yarn generate:types` and commit the result;
- never edit `types/v1.ts` by hand;
- `openapi-typescript`, `ajv` and `ajv-formats` are pinned to exact versions in `package.json`.
  Why: the up-to-date check below compares the generator output byte for byte, so a floating
  generator version would fail it without any change to the document.

**Use in the JSDoc.** The public JSDoc references the generated schemas
(`import('../types/v1.js').components['schemas'][...]`): `send`'s message is
`SendMessageRequest`, its result is `SendMessageResponse & { replayed: boolean }`, and the
`smtp` field of `PidgeyError` is `SmtpReply`. The runtime validation of `send` is unchanged,
because it takes untrusted input: where a field is `unknown` to the validation, an internal type
is kept, so the public signature stays the generated one.

**Declaration build.** `yarn build:types` runs `rm -rf dist && tsc -p tsconfig.build.json`.
`tsconfig.build.json` emits declarations only (`allowJs`, `declaration`, `emitDeclarationOnly`,
`strict`, `nodenext`) for `lib/` and `types/` into the git-ignored `dist/`: `dist/lib/*.d.ts`
and `dist/types/v1.d.ts`. The `types/` declaration is emitted so that the relative
`../types/v1.js` imports inside the `lib` declarations resolve inside the tarball. There is no
`prepack` hook, because the smoke test packs with `--ignore-scripts`: `checks.sh` builds
explicitly instead ([clients.md](../clients.md#checks)). `dist/` is ignored through
`clients/*/dist/` in the root `.gitignore` and by ESLint, and is listed in `pack-files.txt`:
every emitted `.d.ts` is an expected file of the pack check.

**Typecheck versus build.** `yarn typecheck` (JSDoc check, no emit) and `yarn build:types`
(emit) are separate scripts. The typecheck resolves the generated types through the committed
`types/v1.ts`; it needs no build.

### Contract spec

`specs/contract/OpenApi_spec.js` ties the client and the specs to the document, with `ajv`
(draft 2020-12, with `ajv-formats` for `date-time`; `strict: false` so the OpenAPI keywords
such as `example` are tolerated). It reads the document from `../../source/openapi/v1.json`,
relative to the library, so the document must be reachable there: in CI the whole repository is
checked out, and in the `clients` compose service `source/openapi` is mounted read-only
([docker/volumes.md](../docker/volumes.md)). It checks that:

- **Request bodies:** what `send` puts on the wire (text only, html only, both, and with an
  `idempotencyKey`), recorded by `TestServer`, validates against `SendMessageRequest`, and the
  `Idempotency-Key` header matches the document's header schema;
- **Response fixtures:** every shared fixture validates against the schema the document gives
  its status in the `POST /v1/messages` responses;
- **Generated types:** regenerating `types/v1.ts` in memory (the `openapi-typescript` Node API,
  with the options of `yarn generate:types`) equals the committed file. The failure message says
  to run `yarn generate:types`.

**Shared fixtures.** `specs/support/Fixtures.js` exports the valid response bodies the specs
use (the `200` success body and one error envelope per documented status and code), each with
its HTTP status so the contract spec can pick the schema. The `PidgeyClientSend`,
`PidgeyClientSendAbort`, `RetryPolicy` and `RetryPolicyAbort` specs use them. **Rule:** a body
that is off-contract on purpose stays inline in the spec, with a name that says so (an extra
field such as `queueId`, a missing `smtp`, an unknown code, a non-JSON body); every other
response body of a spec comes from `Fixtures.js`, so the contract spec covers it.

### Consumer fixture

`specs/types/consumer.ts` is a strict TypeScript consumer of the installed package, with positive
usage only: it imports `PidgeyClient`, `PidgeyError` and `VERSION` from `pidgey-client`,
builds the client with every option, calls `send` with and without `signal` and
`idempotencyKey`, reads `status`, `smtp.code` and `replayed` into explicitly typed variables and
narrows a caught error with `instanceof PidgeyError`. It imports only `pidgey-client`, so it
needs no `@types/node`. It is never executed: Jasmine does not pick it up (it is not
`*_spec.js`) and ESLint does not lint `.ts`. The pack smoke test type-checks it against the
installed tarball ([Pack smoke test](#pack-smoke-test)); that check is what proves the
`exports` `types` condition and the declarations in `dist/`.

## Scripts

Run from `clients/nodejs/`. Each one exits non-zero on failure.

| Script | Does |
| --- | --- |
| `yarn lint` | ESLint over `lib` and `specs`. |
| `yarn typecheck` | `tsc --allowJs --checkJs --noEmit` over `lib`: the JSDoc types are checked, nothing is emitted. |
| `yarn generate:types` | Regenerates `types/v1.ts` from `../../source/openapi/v1.json`. Run it after a `/v1` document change and commit the result. |
| `yarn build:types` | `rm -rf dist && tsc -p tsconfig.build.json`: emits the `.d.ts` declarations into `dist/`. Run by `checks.sh` before the pack check. |
| `yarn test` | Jasmine over `specs/` (the directories of the unit and contract specs, not `specs/integration/`), files named `*_spec.js`. |
| `yarn coverage` | `c8` running Jasmine over the same specs. Writes `coverage/lcov.info` and **fails below 100 %** of lines, branches, functions and statements, over every file of `lib/` (also those no spec loads). |
| `yarn test:integration` | Jasmine over `specs/integration/` only: `Send_spec.js` sends through the library against a real Pidgey and checks the delivery in Mailpit. Skipped without the `PIDGEY_INTEGRATION_*` environment; see [clients.md](../clients.md#integration). Never part of the coverage. |

The 100 % threshold applies from the first commit, so no later change retrofits it onto
merged code.

## Pack check

The package must publish exactly the files it declares, the emitted `dist/` declarations included, so `checks.sh` runs `yarn build:types` before this check. `clients/nodejs/pack-files.txt` is
the committed list of expected files: one path per line, sorted with `LC_ALL=C sort`, no blank
or comment lines. The check compares it with the `files[].path` values of
`npm pack --dry-run --json`; any file added or missing fails the checks and prints the diff.
Why: an allowlist alone does not stop a file from slipping into the tarball unnoticed, such as
a secret or a spec.

When the package legitimately gains or loses a published file, regenerate the list from
`clients/nodejs/` and review the diff by hand:

```sh
npm pack --dry-run --json \
  | node -e 'let s="";process.stdin.on("data",d=>s+=d).on("end",()=>{for(const f of JSON.parse(s)[0].files)console.log(f.path)})' \
  | LC_ALL=C sort > pack-files.txt
```

If an unexpected file appears, narrow `files` instead of accepting it in the list.

## Pack smoke test

`clients/scripts/smoke_test.sh` runs once per `checks.sh`, after every package has passed. It
packs this library, packs the CLI with `pack_cli.sh` ([cli.md](cli.md#the-dependency-on-the-library))
and installs both tarballs in an empty project, offline and with `--ignore-scripts`. For the
library it asserts what a consumer gets from the tarball: `import { PidgeyClient, PidgeyError }
from 'pidgey-client'` resolves through the `exports` map, and `require('pidgey-client')` works
(`require(esm)`, available from the floor Node.js 22.12), and that `consumer.ts` type-checks
against the installed package (`strict`, `nodenext`, `skipLibCheck: false`, no `@types/node`), so a
wrong `types` path in `exports` or a broken declaration fails it. It also asserts the CLI's packed
dependency range and its `--version` output, which is the library's `VERSION` as installed from
the tarball. What it checks is listed in [clients.md](../clients.md#checks).

**Bumping the library `version`.** The CLI's `yarn.lock` records the `file:../nodejs` entry
with the library version (`"pidgey-client@file:../nodejs": version "<x>"`). Checked with Yarn
1.22: after a bump, `yarn install --frozen-lockfile` in `clients/cli/` still **passes** and
leaves the lockfile as it was, so a stale entry does not fail the checks. Refresh it in the
same change anyway, with a plain `yarn install` in `clients/cli/` (in the compose `clients`
service), so the lockfile does not drift. The packed range follows `package.json` on its own,
with no manual edit.

## Commands

| Command | Does |
| --- | --- |
| `make clients-checks` | Runs `checks.sh` in the compose `clients` service. `make tests` runs it too. |
| `docker compose run --rm clients clients/scripts/checks.sh` | The same, without `make`. |
| `docker compose run --rm clients sh -c "cd clients/nodejs && yarn <lint\|typecheck\|test\|coverage\|generate:types\|build:types>"` | One script on its own. |

The `clients` service is described in [docker/compose.md](../docker/compose.md); it mounts
`source/openapi` read-only, which the contract spec and `yarn generate:types` read.

## CI

The job `clients_checks` runs `clients/scripts/checks.sh` as a matrix over two Node.js
images:

- the floor, `cimg/node:22.12.0`, which proves the `engines` promise;
- the current one, `darthjee/circleci_node:0.2.1`, the image of the other check jobs.

It runs on every branch and tag, in parallel with the other check jobs, and `coverage_final`
requires it. Only the current-version instance uploads coverage to Codacy: `clients/nodejs/coverage/lcov.info`
with the prefix `clients/nodejs`, then `clients/cli/coverage/lcov.info` with the prefix
`clients/cli` ([cli.md](cli.md#ci)). The job also runs the smoke test (through `checks.sh`) and
then asserts that the working tree is unchanged. The clients are not deployed, so no deploy job
requires it. See [deployment/pipeline.md](../deployment/pipeline.md).
