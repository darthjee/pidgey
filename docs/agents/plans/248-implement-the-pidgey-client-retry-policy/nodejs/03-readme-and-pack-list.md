# README and pack list

In `clients/nodejs/README.md`:

- Remove "the automatic retry policy is not implemented yet". In the options table, replace "Validated and stored; the retry behaviour comes in a later release" with the real behavior of `maxRetries`, `retryDelayMs` and `retryAmbiguous`.
- Add a short **Retries** section:
  - what is retried by default, and what never is;
  - `retryAmbiguous` and its duplicate risk;
  - the backoff: `retryDelayMs`, doubling, capped at 8000 ms, including a larger base;
  - the shared `Idempotency-Key`;
  - the last error being returned, with `possiblySent` if any attempt was ambiguous;
  - the worst case, about 91.5 s with the defaults;
  - `send(msg, { signal: AbortSignal.timeout(ms) })` to bound the whole call.

Add `lib/retry.js` to `clients/nodejs/pack-files.txt`, in sorted order, and confirm it with `npm pack --dry-run` (run by `make clients-checks`).

## Files to Change

- `clients/nodejs/README.md`: the retry documentation.
- `clients/nodejs/pack-files.txt`: `lib/retry.js` added.
