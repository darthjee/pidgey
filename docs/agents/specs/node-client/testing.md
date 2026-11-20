# Testing

Spec of how `pidgey-client` and `pidgey-cli` are tested and which CI jobs run the tests.

## Approach

- **Real `fetch`, local server.** Library specs exercise HTTP with real `fetch` against an
  in-process `node:http` server started per spec, not a stubbed `globalThis.fetch`: only a
  real server proves `redirect: 'error'`, timeouts, non-JSON bodies and abort wiring.
  Stubbing `fetch` cannot prove those behaviours.
- **Fast backoff.** Backoff is tested with a tiny `retryDelayMs` plus a spy on the delay
  function, so specs stay fast without fake timers.
- **End-to-end in CI.** An end-to-end run is part of CI, not opt-in
  ([Integration](#integration)). Mailpit already exists in `docker-compose.yml`, so a real
  delivery assertion is cheap.
- Specs mirror the source tree of each package.

## Library (`clients/nodejs/specs/`)

| Spec | Covers |
| --- | --- |
| `specs/client/PidgeyClient_spec.js` | Configuration validation (`token`, `baseUrl`, scheme, trailing slash, `maxRetries`, `timeoutMs`); the cleartext rule ([library.md](library.md#cleartext-transport)): the loopback forms accepted without opt-in (`localhost`, `LOCALHOST`, `127.0.0.1`, `127.1`, `[::1]`), the non-exempt forms rejected without opt-in (`localhost.`, `*.localhost`, `[::ffff:127.0.0.1]`, `0.0.0.0`, any other host), `allowInsecureHttp: true` accepting them, the option ignored with `https://`, and the `CLIENT_CONFIGURATION` message never containing the token; invalid `send` argument; the header set; no `Origin` header; `redirect: 'error'`; no request made when the configuration is invalid. |
| `specs/client/PidgeyClientSend_spec.js` | Body mapping for `to`/`subject`/`text`/`html`; unknown keys not sent; `undefined` fields omitted; the request path; the resolved `{ status, smtp }` passthrough; a `200` body without `smtp`. |
| `specs/client/PidgeyError_spec.js` | Envelope mapping to `statusCode`/`code`/`message`/`details`/`smtp`; the fallback to the HTTP status; `INVALID_RESPONSE` for a non-JSON body; the retained response body ([errors-and-retry.md](errors-and-retry.md#response-body)): the 64 KiB read cap (by bytes after decompression, and by `Content-Length` without reading), the 4 KiB cut and `bodyTruncated`, no split character, token redaction including a token straddling the 4 KiB boundary, `body` and `bodyTruncated` absent from `JSON.stringify` and `util.inspect`, and `body` absent on the no-response codes. |
| `specs/client/RetryPolicy_spec.js` | One case per row of the [retry table](errors-and-retry.md#retry-table) (each `SMTP_*` code, unknown `SMTP_*` on `502`/`503`/`504`, network errors); no retry on `400`/`401`/`403`/`409`/`429`/`500`, 3xx and a non-JSON `200`; `ABORTED` from a caller signal during an attempt and during the backoff wait; attempt count for `maxRetries` `0`, `1` and `2`; backoff growing and capped; the last error surfacing when retries are exhausted; a timeout classified as an ambiguous `NETWORK_ERROR`; `retryAmbiguous` off and on; `possiblySent` for every code, including the "any attempt was ambiguous" rule; `possiblySent` for `ABORTED`: before any request, in flight (including while the body is being read), during the backoff after unambiguous failures, and after an earlier ambiguous attempt. |
| `specs/contract/OpenApi_spec.js` | Request bodies built by `send` and the response fixtures used by the specs validate against the OpenAPI document; the generated `types/` file is up to date with it ([openapi.md](openapi.md#contract-tests)). |

## CLI (`clients/cli/specs/`)

| Spec | Covers |
| --- | --- |
| `specs/cli/Arguments_spec.js` | Parsing of every flag; `--token-file`/`--url` versus `PIDGEY_TOKEN`/`PIDGEY_URL` precedence; one trailing newline (`\n` or `\r\n`) trimmed from the token file; missing, unreadable and empty token file → exit `2`; a token file over 4 KiB → exit `2`; a non-regular file (`/dev/stdin`, a FIFO, a directory; a symlink to a regular file is accepted) → exit `2`; a control character (CR, LF, NUL, tab) left after the trim → exit `2`, with a message naming the path only; the mode warning taken from `fstat` of the descriptor read; the group/other-readable token file warning; `--token` rejected as an unknown flag; `--allow-insecure-http` parsed, and exit `2` for a non-loopback `http://` `--url` without it; `--help` and `--version`; unknown flag; repeated flag; missing token or URL. |
| `specs/cli/Body_spec.js` | Stdin read when neither body flag is given and stdin is not a TTY; no stdin read when `--html` is present; unreadable stdin. |
| `specs/cli/ExitCode_spec.js` | `0` on a sent message, `1` on a `PidgeyError`, `2` on a usage error and on `CLIENT_CONFIGURATION`, `130` on SIGINT, with nothing printed on success. |

## Cross-cutting

- **Backend, OpenAPI diff:** the `/v1` breaking-change diff against `main` runs in the
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
- **Lint:** `yarn lint` in both packages, with the same rules as `frontend/`, green.

## Integration

`specs/integration/Send_spec.js`, run by the dedicated CI job `clients_integration` and a
matching `Makefile` target:

1. The job boots the compose stack: Pidgey backend, MySQL, Mailpit.
2. It seeds an organization, a `send`-scoped API token and an SMTP connection pointing at
   Mailpit.
3. It sends through the library **and** through the `pidgey` CLI, and asserts delivery
   through Mailpit's HTTP API.
4. It also covers a real `409 NO_SMTP_CONNECTION` for an organization without an active
   connection, and a real `401` for a revoked token.

Locally the spec is skipped unless the environment points at a running stack.

## CI and `make tests`

| Job | Runs |
| --- | --- |
| `clients_checks` | Lint and tests of both packages, in parallel with the existing `backend_checks`, `backend_tests` and `frontend_checks` jobs. |
| `clients_integration` | The [integration](#integration) spec against Pidgey + MySQL + Mailpit. |

- `clients_checks` ships with the library scaffold, so `clients/` is checked from its first
  commit: [contributing.md](../../contributing.md) requires the check job of a new
  application folder to exist before changes to that folder are merged.
- The `Makefile` gains the client targets, so `make tests` keeps being the single command
  that runs every check. The `Makefile` target and the CI job call the **same** commands:
  `clients_checks` must not become a job that `make tests` skips.
- Done means: `clients_checks` and `clients_integration` green on a branch that touches
  `clients/`, and `make tests` green locally.

## Extra checks

**Pending decision** (#220): which extra checks the packages carry — pack smoke test, `.d.ts` type check, CLI subprocess tests, coverage threshold, Node.js version matrix — and, for each adopted one, its package, command, `Makefile` target and CI job.
