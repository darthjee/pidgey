# Library and errors/retry specs

Write `docs/agents/specs/node-client/library.md` from #219's "Node client public surface" and library parts of the edge cases:
- `new PidgeyClient({ token, baseUrl, maxRetries, retryDelayMs, retryAmbiguous, timeoutMs })` with defaults and validation (`CLIENT_CONFIGURATION`).
- `send(message, { signal })`: v1 field whitelist (`to`, `subject`, `text`, `html`), `undefined` omitted, no value validation; headers (`Authorization`, `Content-Type`, `Accept`, no `Origin`); `redirect: 'error'`; caller abort; per-attempt timeout; success body passed through unchanged; concurrency; `http://` accepted and documented.
- The usage example from #219 (placeholder token, `example.com`).

Write `docs/agents/specs/node-client/errors-and-retry.md` from "Retry and failure classification" and the error edge cases:
- `PidgeyError` fields (`statusCode`, `code`, `message`, `details`, `smtp`, `possiblySent`, retained body) and mapping from the envelope; fallback to HTTP status.
- Client-local codes `CLIENT_CONFIGURATION`, `NETWORK_ERROR` (ambiguous vs unambiguous), `INVALID_RESPONSE`, `ABORTED`, documented as outside the API taxonomy and extensible.
- The retry table by `error.code`, `retryAmbiguous`, backoff (500 ms doubling, 8000 ms cap, `retryDelayMs`), `maxRetries`, the "last error / any attempt ambiguous" rules, no `Retry-After`.
- Cross-check every `SMTP_*` code and status against `docs/agents/email/failures.md` and `docs/agents/api/conventions/errors.md`; record any conflict for the PR description.
- Link #221 as the separate Idempotency-Key issue.

## Files to Change
- `docs/agents/specs/node-client/library.md` — new.
- `docs/agents/specs/node-client/errors-and-retry.md` — new.
