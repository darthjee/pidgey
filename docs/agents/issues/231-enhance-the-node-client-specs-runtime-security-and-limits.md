# Issue: Enhance the Node client specs: runtime security and limits

## Description

#225 settled three things for the Node client specs (`docs/agents/specs/node-client/`): the supply chain, how the CLI gets its token, and the public OpenAPI route. Four runtime points were left open, and #225 re-pointed their `**Pending decision**` markers to this issue. This issue records a decision for each of the four. It is **docs-only**; implementation belongs to the later sub-issues of #219 (parent epic).

## Problem

- **Cleartext transport** (`library.md`). `library.md` accepts an `http://` `baseUrl` silently, so a mistyped production URL sends the Bearer token in clear text.
- **Retained response body** (`errors-and-retry.md`).
  - `PidgeyError` keeps the original response body with no bound.
  - Pidgey's own error bodies cannot carry secrets, but a body from something other than Pidgey can: a proxy page, or a wrong `baseUrl` pointing at a server that reflects request headers, which would echo the token.
  - Nothing limits how much is read or what ends up in logs.
- **Stdin size cap** (`cli.md`). The CLI reads stdin without a limit. The API has no body-size limit either (`docs/agents/api/pending.md`), so `pidgey < /dev/zero` or an accidental huge pipe reads until memory runs out.
- **Connection reuse and worst-case duration** (`library.md`).
  - Connection reuse is undecided.
  - A call can take about 91.5 s with the defaults (3 attempts × 30 s + 0.5 s + 1 s of backoff), and nothing documents how to bound it.
  - `ABORTED` always reports `possiblySent: false`, even when the abort cuts a request that was already in flight, which under-reports a possible delivery.

## Expected Behavior

- Every `**Pending decision** (#231)` marker in `docs/agents/specs/node-client/` is replaced by the decision below, and the hub's pending-decisions table no longer has a #231 row.
- `packaging.md` ("What is breaking") and `testing.md` reflect the new rules.
- The `security` agent reviews the decisions.
- The backend change these decisions call for (API request size limits) is tracked on its own, in #233, not in this epic.

## Solution

### Cleartext transport (`library.md`, `cli.md`)

- An `http://` `baseUrl` is accepted **without opt-in only for a literal loopback host**: `localhost`, any `127.0.0.0/8` address, or `[::1]`.
  - The check runs on the hostname after WHATWG URL parsing, so `http://LOCALHOST` and `http://127.1` are exempt.
  - There is no DNS lookup.
  - `http://localhost.` (trailing dot), `*.localhost`, `http://[::ffff:127.0.0.1]` (IPv4-mapped) and `http://0.0.0.0` are **not** exempt.
- Any other `http://` host needs an explicit opt-in: the library option `allowInsecureHttp: true`, or the CLI flag `--allow-insecure-http`.
  - Without it the library rejects with `CLIENT_CONFIGURATION` before any request, and the CLI exits `2`.
  - The error message names the reason (cleartext token) and never the token.
- With an `https://` URL the option or flag is accepted and has no effect.
- No warning is printed in any case.
- There is no environment variable for the opt-in; one can be added later without a breaking change.
- Redirects cannot downgrade the connection to cleartext: any 3xx is already `INVALID_RESPONSE`.
- **Rejected:**
  - Staying silent: it leaks the token on a mistyped URL.
  - Warning only: easy to ignore, and a library warning is noise for server consumers.
  - Requiring the opt-in for every `http://` host, loopback included: friction with no gain, because loopback traffic never leaves the host.

### Retained response body (`errors-and-retry.md`)

- **Read cap.** Every response, 2xx included, is read up to **64 KiB**.
  - The cap counts bytes after `fetch`'s transparent decompression, which also guards against a decompression bomb.
  - A `Content-Length` above the cap is rejected without reading the body.
  - A longer body aborts the read and becomes `INVALID_RESPONSE`, with `possiblySent: true` on a 2xx, as for any broken 2xx body.
  - The bytes are decoded as UTF-8 with replacement characters.
- **Redaction.**
  - Every occurrence of the literal token in the text read is replaced with `[redacted]`.
  - Redaction runs before any cut, so a token straddling the cut cannot survive partially.
  - Encoded forms (URL-encoded, base64) are not searched.
- **Retention.**
  - `PidgeyError` keeps the raw text as `body`, cut to **4 KiB** without splitting a character, with `bodyTruncated: true` when it was cut.
  - `body` is absent when there was no HTTP response (`CLIENT_CONFIGURATION`, `ABORTED`, `NETWORK_ERROR`).
- **Logging.**
  - `body` and `bodyTruncated` are **non-enumerable**, so `JSON.stringify`, `util.inspect` (without `showHidden`) and logger error serializers do not include them.
  - The parsed fields (`code`, `message`, `statusCode`, `details`, `smtp`, `possiblySent`) stay enumerable.
  - `err.body` still works when read on purpose.
- **CLI.** The CLI does not print `body`; its stderr output is unchanged.
- **Rejected:**
  - No caps: a broken or hostile server can push unbounded memory into the client.
  - Keeping only the parsed envelope: it loses the proxy page needed to diagnose.
  - A custom `toJSON` / `util.inspect.custom`: more code, and logger serializers bypass it.

### Stdin size cap (`cli.md`)

- The CLI stops reading stdin after **10 MiB**, counted in bytes before UTF-8 decoding.
  - Exactly 10 MiB is accepted; one byte more exits `2` with a message naming the limit, and nothing is sent.
  - Reading stops as soon as the input goes over the cap; the rest is not drained.
- `--text`/`--html` are not capped: the shell already limits argument size.
- A stdin that never closes (`tail -f | pidgey`) blocks forever. There is no stdin timeout, and the README says so.
- The library does **not** cap `text`/`html`. The caller already holds the string, and the API is the only validator: when the API defines a limit, its error reaches the consumer as a normal `PidgeyError`.
- The cap is not configurable; a `--max-body-size` flag can be added later without a breaking change.
- If the API limit (#233) ends up below 10 MiB, the CLI cap follows it.
- **Rejected:**
  - A library cap: it duplicates a rule the API owns.
  - A configurable cap: nobody needs it yet.
  - No cap: no protection against an endless pipe.

### Connection reuse and worst-case duration (`library.md`, `errors-and-retry.md`)

- **Connection reuse.**
  - The library uses Node's global `fetch` dispatcher as is, with its default keep-alive. There is no custom `Agent`, no dependency and no option.
  - A custom `Agent` would need the `undici` package, which breaks the zero-runtime-dependency rule.
  - The README notes that connections are reused, and that undici's `setGlobalDispatcher` tunes this process-wide.
- **The CLI does not hang.** The CLI process exits right after a send, without waiting on an idle keep-alive socket. The CLI subprocess tests check this.
- **Worst-case duration.** `timeoutMs` stays per attempt. The README states the worst case with the defaults: about **91.5 s**, reached only by retried, unambiguous failures that each run to `timeoutMs`.
- **Overall deadline.**
  - There is no new option.
  - The README shows `send(msg, { signal: AbortSignal.timeout(ms) })` as the way to bound the whole call.
  - The CLI has no `--deadline` flag.
- **`ABORTED` and `possiblySent` (a correction).** A request counts as dispatched once `fetch` has been called.
  - `possiblySent: true` when the abort fires after a request was dispatched and before its response body was fully read, or when an earlier attempt was ambiguous (the existing "any attempt" rule).
  - `possiblySent: false` when the abort fires before any request, or during the backoff after only unambiguous failures.
  - `ABORTED` is still never retried.
  - The `ABORTED` row of the client-local codes table and the Ambiguous failures table are updated to match.
- **Rejected:**
  - A `deadlineMs` option: it duplicates `AbortSignal`.
  - Lower default timeouts: they would cut legitimate slow SMTP sends into ambiguous errors.
  - A custom `fetch` option: API surface nobody needs yet.

### Spec changes in this issue

- `library.md`:
  - the cleartext rule and the `allowInsecureHttp` option row;
  - the keep-alive note;
  - the worst-case note with the `AbortSignal.timeout` recipe.
- `errors-and-retry.md`:
  - the `body` / `bodyTruncated` fields;
  - the read cap and the redaction;
  - the `ABORTED` correction.
- `cli.md`:
  - the `--allow-insecure-http` flag;
  - the stdin cap and its exit `2`.
- `packaging.md` ("What is breaking"):
  - lowering the stdin cap is breaking;
  - removing the loopback exemption is breaking.
- `testing.md`: a spec row for each decision, including the edge cases above.
- The hub: the #231 row of the pending-decisions table and every `**Pending decision** (#231)` marker are removed.

### Out of scope

- API request size limits (#233).
- The `Idempotency-Key` header (#221).
- Implementing any of this (later sub-issues of #219).
- The #225 decisions.
- Proxy support or a custom `fetch`.
- The browser target.

Owners: `product-owner`, with a review by `security`.

## Benefits

- A misconfigured URL can no longer leak the token in clear text, and plain local development keeps working.
- A broken or hostile server cannot push unbounded memory into the client or the token into logs.
- The CLI cannot be made to read endless input.
- The `ABORTED` correction stops callers who bound a call with a deadline from resending, and duplicating, a message that may already have been delivered.
- The epic's specs have no runtime-security pending decision left before implementation starts.
