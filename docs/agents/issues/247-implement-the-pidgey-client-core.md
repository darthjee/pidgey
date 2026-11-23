# Issue: Implement the pidgey-client core

## Description

- Parent epic: #219 (wave 2, created by #228).
- Specs under `docs/agents/specs/node-client/`:
  - `library.md`: every section;
  - `errors-and-retry.md`: `PidgeyError`, Response body, Client-local codes, Ambiguous failures, Possibly sent;
  - `testing.md`: Approach (with its Edge-case rules) and Library.
- Owners: `nodejs` (package), `product-owner` (as-built docs and the spec addition below).
- Depends on #246 (merged). Does not wait for #237: the specs run against an in-process server. Blocks #248 and #250; #249 uses `VERSION`.
- Today (as built by #246), `clients/nodejs/` holds:
  - `PidgeyClient`, which stores a frozen copy of its options;
  - `PidgeyError(message, { code })`, which sets `name` and `code`;
  - specs under `specs/lib/`, mirroring `lib/`;
  - `c8` at 100 %, the pack check and `clients_checks`.

## Problem

The library scaffold exists, but `pidgey-client` cannot send anything yet. Its configuration is not validated, and no failure is mapped to a `PidgeyError`.

## Expected Behavior

- `new PidgeyClient(options)` throws `PidgeyError` `CLIENT_CONFIGURATION` for every configuration mistake, before any request. That includes the cleartext rule.
- `send` resolves with the success body unchanged, plus `replayed`.
- `send` rejects with a `PidgeyError` carrying the right `code`, `statusCode`, `message`, `details`, `smtp` and `possiblySent` for every error family:
  - an envelope error;
  - `INVALID_RESPONSE`;
  - `NETWORK_ERROR`, ambiguous or not;
  - `ABORTED`;
  - `CLIENT_CONFIGURATION`.
- Every `send` carries an `Idempotency-Key`: the caller's, or one generated once per call (#248 reuses it across retries).
- `body` and `bodyTruncated` are redacted, capped and non-enumerable.
- `VERSION` is exported and equals `package.json`'s `version`.
- Coverage stays at 100 %, with zero runtime dependencies and the pack list updated only if a published file is added.
- No retry yet: one attempt per `send`, whatever `maxRetries` says. That is #248.

### Testing

- Specs follow `testing.md`, sections Approach and Library. They use real `fetch` against an in-process `node:http` server:
  - on `127.0.0.1`, port `0`;
  - torn down with `closeAllConnections()` before `close()`.
- The spec files mirror `lib/` under `specs/lib/` (as built by #246), not the `specs/client/` names of the spec table, which follows "specs mirror the source tree".
- The rows of `PidgeyClient_spec`, `PidgeyClientSend_spec`, `PidgeyError_spec` and `Version_spec` are covered, except their retry cases, which are #248's:
  - the key reused across retries;
  - `ABORTED` during backoff;
  - the "any attempt" rule.
- `make clients-checks` passes, and `clients_checks` passes on both matrix jobs.

## Solution

1. **`new PidgeyClient(options)`** (`library.md`, `new PidgeyClient(options)` and Cleartext transport):
   - validates `token`, `baseUrl` and the cleartext rule (loopback exemptions on the WHATWG hostname, no DNS);
   - normalizes `baseUrl` (drops the trailing slash, keeps a path prefix);
   - validates the options, each failure being `CLIENT_CONFIGURATION`:
     - `maxRetries`: a non-negative integer;
     - `timeoutMs`: a **positive finite integer**;
     - `retryDelayMs`: a **non-negative finite integer**;
     - `retryAmbiguous` and `allowInsecureHttp`: **booleans** when given;
   - stores `maxRetries`, `retryDelayMs` and `retryAmbiguous` with their defaults, for #248 to use;
   - writes messages that never contain the token or the URL (nor its userinfo).
2. **`PidgeyError`** (`errors-and-retry.md`, `PidgeyError`, Response body, Client-local codes):
   - fields `statusCode`, `code`, `message`, `details`, `smtp`, `possiblySent`, plus non-enumerable `body` and `bodyTruncated`;
   - mapping from the envelope, with `INVALID_RESPONSE` as the fallback;
   - the 64 KiB read cap (by bytes after decompression, and by `Content-Length` without reading);
   - UTF-8 decoding, token redaction before parsing and before the cut (configured and trimmed forms, longer first), and the 4 KiB cut without splitting a character.
3. **`client.send(message, { signal, idempotencyKey })`** (`library.md`, `client.send`, Idempotency key, Success):
   - the argument check, the `to`/`subject`/`text`/`html` whitelist, `undefined` omitted;
   - the request and its headers (no `Origin`), `redirect: 'error'`;
   - the `Idempotency-Key`: the caller's (validated: 1–255 characters in `0x21`–`0x7E`) or one `crypto.randomUUID()` per call;
   - the success passthrough plus `replayed`;
   - a non-JSON 2xx rejected as `INVALID_RESPONSE`.
4. **Failure classification** (`errors-and-retry.md`, Ambiguous failures, Possibly sent):
   - `possiblySent` per code;
   - ambiguous versus unambiguous `NETWORK_ERROR`;
   - the per-attempt `timeoutMs` as an ambiguous `NETWORK_ERROR`;
   - any 3xx as `INVALID_RESPONSE` keeping the status;
   - `ABORTED` before a request (`false`) or in flight, including while reading the body (`true`);
   - `409 IDEMPOTENCY_KEY_IN_USE` with `possiblySent: true`, and `422 IDEMPOTENCY_KEY_REUSED` with `false`.

   Classification is exposed so #248 can decide retries from it without reshaping `send`.
5. **`VERSION`** (`library.md`, Exports): a literal string constant exported from `lib/index.js`. A spec reads `package.json` and asserts the two are equal, since no build step injects it.
6. **Runtime constraints:** only `fetch`, `AbortSignal` and `crypto.randomUUID()`, using the global `fetch` dispatcher; no custom `Agent`, no dependency.
7. **JSDoc** on every public export. It is checked by `yarn typecheck`; the generated schema types are #250's.
8. **README** (`clients/nodejs/README.md`):
   - the usage example;
   - the errors and their client-local codes, noting that new codes may be added;
   - abort, and bounding a whole call with `send(msg, { signal: AbortSignal.timeout(ms) })`;
   - the note that connections are reused and that undici's `setGlobalDispatcher` tunes this process-wide.

   It drops "does not send email yet". #248 adds the retry options' text.
9. **Docs** (owner `product-owner`):
   - `docs/agents/clients/nodejs.md` describes the as-built surface and behaviour, replacing "Current surface";
   - `docs/agents/specs/node-client/library.md` and `errors-and-retry.md` (the `CLIENT_CONFIGURATION` row) record the validation rules for `timeoutMs`, `retryDelayMs`, `retryAmbiguous` and `allowInsecureHttp`;
   - the spec tables of `testing.md` name the `specs/lib/` paths actually used.

Out of scope:
- the retry policy, backoff and abort during backoff (#248);
- the generated types and the contract spec (#250);
- the CLI (#249, #251);
- the integration spec (#252).

## Benefits

- The library can send, and reports every failure as one error class with a total classification, which #248's retry decision and the CLI build on.
- The safety rules hold from the first `send`:
  - no cleartext token by accident;
  - no token echoed in errors or logs;
  - bounded memory on hostile responses;
  - an idempotency key on every request.
- `VERSION` is ready for the CLI's `--version`.
