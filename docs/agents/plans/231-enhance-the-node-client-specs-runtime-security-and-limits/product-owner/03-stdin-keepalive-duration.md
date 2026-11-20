# Stdin cap, keep-alive and worst-case duration

Replace the stdin marker (`cli.md:104`) and the connection reuse / duration marker
(`library.md:150`) with their decisions.

- **`cli.md`, Body and stdin:**
  - Stdin is read up to **10 MiB**, counted in bytes before UTF-8 decoding. Exactly 10 MiB
    is accepted; one byte more exits `2` with a message naming the limit, and nothing is
    sent. Reading stops as soon as the input goes over the cap; the rest is not drained.
  - `--text`/`--html` are not capped: the shell already limits argument size.
  - A stdin that never closes blocks forever; there is no stdin timeout, and the README
    says so.
  - The cap is not configurable; a `--max-body-size` flag can be added later.
  - If the API limit (#233) ends up below 10 MiB, the CLI cap follows it.
  - Rejected: a library cap, a configurable cap, no cap.
  - Add "stdin over 10 MiB" to the Exit codes `2` row.
- **`library.md`, Message:** state that the library does not cap `text`/`html`: the API is
  the only validator, and its limit error (#233) reaches the consumer as a normal
  `PidgeyError`.
- **`library.md`, Timeout** (replaces the marker):
  - `timeoutMs` stays per attempt.
  - The worst case with the defaults is about 91.5 s (3 × 30 s + 0.5 s + 1 s), reached only
    by retried, unambiguous failures.
  - The README shows `send(msg, { signal: AbortSignal.timeout(ms) })` to bound the whole
    call; there is no `deadlineMs` option and no CLI `--deadline` flag.
  - Rejected: a `deadlineMs` option, lower default timeouts.
- **`library.md`, new "Connections" subsection** (next to Concurrency):
  - global `fetch` dispatcher, default keep-alive; no custom `Agent`, no dependency, no
    option;
  - the README mentions undici's `setGlobalDispatcher` for process-wide tuning;
  - a custom `Agent` would break the zero-runtime-dependency rule;
  - rejected: a custom `fetch` option.
- **`cli.md`:** the process exits right after a send, without waiting on an idle
  keep-alive socket.
- **`testing.md`:**
  - Extend the `Body_spec.js` row: exactly 10 MiB accepted; 10 MiB + 1 byte → exit `2`
    with nothing sent; reading stops at the cap.
  - Add the no-hang expectation (the CLI exits promptly after a send against a keep-alive
    server). Mark it as running with the CLI subprocess tests whose adoption is #220's
    pending decision; do not resolve #220.

## Files to Change
- `docs/agents/specs/node-client/cli.md` — stdin cap, exit code `2` row, no-hang note, marker removed.
- `docs/agents/specs/node-client/library.md` — Message note, Timeout worst case and deadline recipe, Connections subsection, marker removed.
- `docs/agents/specs/node-client/testing.md` — `Body_spec.js` row and the no-hang expectation.
