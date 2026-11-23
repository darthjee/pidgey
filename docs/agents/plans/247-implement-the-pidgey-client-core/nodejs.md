# Nodejs Plan: Implement the pidgey-client core

Main plan: [plan.md](plan.md)

## Shared contracts

- **What you produce:**
  - the public surface, the options and validation, and the client-local codes, exactly as in [plan.md](plan.md#shared-contracts);
  - the spec files under `specs/lib/`, mirroring `lib/`.
- **What #248 relies on:**
  - `PidgeyClient` stores `maxRetries`, `retryDelayMs` and `retryAmbiguous` validated with their defaults;
  - `send` delegates one attempt to an internal method, `#attempt(message, key, signal)` or a module function. It resolves the success value, or rejects with a fully classified `PidgeyError` whose `possiblySent` says whether that attempt was ambiguous.

  #248 then loops around it with the same key, without reshaping `send`.

## Steps

- [01 — Configuration, cleartext rule and VERSION](nodejs/01-configuration-and-version.md)
- [02 — PidgeyError and response handling](nodejs/02-error-and-response.md)
- [03 — send: request, success and failure classification](nodejs/03-send.md)
- [04 — README and pack list](nodejs/04-readme-and-pack-list.md)

## CI Checks

- `clients/nodejs`: `make clients-checks` (CI job: `clients_checks`, on the `cimg/node:22.12.0` and `darthjee/circleci_node:0.2.1` images). It runs:
  - `yarn install --frozen-lockfile`;
  - `yarn lint`, `yarn typecheck`;
  - `yarn coverage` (100 %);
  - the pack check against `pack-files.txt`.

## Notes

- **No runtime dependency.** Use only the global `fetch`, `AbortController`/`AbortSignal`, `crypto.randomUUID()` (global `crypto`), `TextDecoder`, `URL` and `Response.body` streams. Do not import `undici` or any `node:` module in `lib/`. The `node:` modules are fine in specs (`node:http`, `node:util`, `node:fs`, `node:zlib`).
- **Keep the token out of inspectable state.** The stub's public `this.options` (frozen, enumerable) would print the token through `console.log(client)`. Store the configuration in private class fields (`#token`, `#baseUrl`, …) and drop `options`. The stub specs that assert `options` are replaced.
- **Ambiguous versus unambiguous `NETWORK_ERROR`.** `fetch` does not say whether the request was written, so classify from `error.cause?.code`, walking nested `cause`s:
  - unambiguous: `ECONNREFUSED`, `ENOTFOUND`, `EAI_AGAIN`, `EHOSTUNREACH`, `ENETUNREACH`, and TLS/certificate failures (`ERR_TLS_*`, `CERT_*`, `*_CERT_*`, `DEPTH_ZERO_SELF_SIGNED_CERT`, `UNABLE_TO_VERIFY_LEAF_SIGNATURE`);
  - ambiguous: everything else, including `ECONNRESET`, `UND_ERR_SOCKET` and the per-attempt timeout. This errs on the safe side, as the spec does for `ABORTED`.

  Record the list in a JSDoc comment.
- **Timeout versus caller abort.** Combine one per-attempt `AbortController` (fired by a `setTimeout(timeoutMs)` that is always cleared) with the caller's `signal` through `AbortSignal.any`, which is available in Node ≥ 20.3. Decide which one fired by checking `signal?.aborted` first. A caller abort → `ABORTED`; otherwise → timeout `NETWORK_ERROR` (ambiguous). Both checks cover the fetch call and the body read.
- **Specs** use real `fetch` against a per-spec `node:http` server on `127.0.0.1`, port `0`, with `closeAllConnections()` before `close()` (`testing.md`, Approach). A shared helper under `specs/support/` (for example `TestServer.js`) records requests and scripts replies: status, headers, body, delay, a destroyed socket, a gzip body, an idle keep-alive socket. Keep the timeouts in specs tiny (`timeoutMs: 50`) so the suite stays fast.
- The TLS-failure classification cannot be reached with a real server cheaply. Cover the classifier as a pure function over synthetic `cause` objects. The end-to-end specs cover refused (a closed port) and reset (a destroyed socket).
