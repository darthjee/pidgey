# Node.js client (`pidgey-client`)

The library for Pidgey's `/v1` API, in `clients/nodejs/`. It has the working **core**: the
configuration, one `send` attempt, `PidgeyError` and the failure classification. **Retries are not
built yet** (#248): a `send` makes exactly one attempt, whatever `maxRetries` says. The OpenAPI
types and the contract spec (#250) are not built either. The rules are specified in
[specs/node-client/library.md](../specs/node-client/library.md) and
[specs/node-client/errors-and-retry.md](../specs/node-client/errors-and-retry.md); this page
records what was built and links the rules instead of copying them.

## Package

| Field | Value |
| --- | --- |
| Name | `pidgey-client` |
| Version | `0.1.0`, the first release line; nothing is published before the epic's release. |
| Module system | ESM (`"type": "module"`). |
| Entry point | `exports` maps `.` to `lib/index.js`. |
| Node.js | `engines.node` is `>=22.12.0`. |
| Published files | The `files` allowlist holds `lib/` only (seven modules); npm adds `package.json` and `README.md`. |
| Dependencies | No runtime dependency. Why: the library stays dependency-free to keep its supply chain empty. |
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
| `lib/PidgeyClient.js` | The client: the `send` call and one attempt. Its configuration is in private fields, so the token never shows in `console.log(client)`, `JSON.stringify(client)` or a logger. It keeps no per-call state, so concurrent sends are independent. |
| `lib/PidgeyError.js` | The error class. |
| `lib/config.js` | Option validation, the defaults and the cleartext rule. |
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
| `maxRetries` | `2` | A non-negative integer. Validated, but unused until #248. |
| `retryDelayMs` | `500` | A non-negative integer. Validated, but unused until #248. |
| `retryAmbiguous` | `false` | A boolean. Validated, but unused until #248. |
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
- **Abort and timeout.** The caller's signal and a per-attempt timer (`timeoutMs`) are combined
  with `AbortSignal.any`, and the timer is cleared when the attempt ends. See
  [Failure classification](#failure-classification).

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

The specs mirror `lib/` under `clients/nodejs/specs/lib/`, plus a shared helper:

- `config_spec.js`, `request_spec.js`, `response_spec.js` (the body read, redaction and cut),
  `response_map_spec.js` (the mapping of a response) and `Version_spec.js`;
- `index_spec.js`, `PidgeyClient_spec.js`, `PidgeyClientSend_spec.js`,
  `PidgeyClientSendAbort_spec.js` and `PidgeyError_spec.js`;
- `specs/support/TestServer.js`, a scripted in-process `node:http` server on `127.0.0.1` with
  port `0`: it records the requests and answers from a script (JSON or raw bodies, gzip,
  delays, a destroyed socket, a stalled body). The specs use real `fetch` against it.

The coverage threshold is 100 %, over every file of `lib/`.

## Scripts

Run from `clients/nodejs/`. Each one exits non-zero on failure.

| Script | Does |
| --- | --- |
| `yarn lint` | ESLint over `lib` and `specs`. |
| `yarn typecheck` | `tsc --allowJs --checkJs --noEmit` over `lib`: the JSDoc types are checked, nothing is emitted. |
| `yarn test` | Jasmine over `specs/`, files named `*_spec.js`. |
| `yarn coverage` | `c8` running Jasmine. Writes `coverage/lcov.info` and **fails below 100 %** of lines, branches, functions and statements, over every file of `lib/` (also those no spec loads). |

The 100 % threshold applies from the first commit, so no later change retrofits it onto
merged code.

## Pack check

The package must publish exactly the files it declares. `clients/nodejs/pack-files.txt` is
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

## Commands

| Command | Does |
| --- | --- |
| `make clients-checks` | Runs `checks.sh` in the compose `clients` service. `make tests` runs it too. |
| `docker compose run --rm clients clients/scripts/checks.sh` | The same, without `make`. |
| `docker compose run --rm clients sh -c "cd clients/nodejs && yarn <lint\|typecheck\|test\|coverage>"` | One script on its own. |

The `clients` service is described in [docker/compose.md](../docker/compose.md).

## CI

The job `clients_checks` runs `clients/scripts/checks.sh` as a matrix over two Node.js
images:

- the floor, `cimg/node:22.12.0`, which proves the `engines` promise;
- the current one, `darthjee/circleci_node:0.2.1`, the image of the other check jobs.

It runs on every branch and tag, in parallel with the other check jobs, and `coverage_final`
requires it. Only the current-version instance uploads `clients/nodejs/coverage/lcov.info` to
Codacy, with the prefix `clients/nodejs`. The clients are not deployed, so no deploy job
requires it. See [deployment/pipeline.md](../deployment/pipeline.md).
