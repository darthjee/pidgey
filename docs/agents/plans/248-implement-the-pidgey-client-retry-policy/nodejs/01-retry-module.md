# Retry module

Create `clients/nodejs/lib/retry.js` with the pure helpers described in the shared contracts of [plan.md](../plan.md). Give every export JSDoc and use no runtime dependency.

- `MAX_BACKOFF_MS = 8000`.
- `backoffDelay(retryDelayMs, retry)` returns `Math.min(retryDelayMs * 2 ** (retry - 1), MAX_BACKOFF_MS)`. `retry` is 1-based. The JSDoc states that a base above 8000 ms is capped too.
- `shouldRetry(error, retryAmbiguous)` follows the six ordered rules in the contracts:
  - the never-retried set;
  - `possiblySent` gated on `retryAmbiguous`;
  - the transient codes;
  - `SMTP_TIMEOUT`;
  - unknown `SMTP_*` on `503` or `504`;
  - everything else, which is not retried.

  Make the known `SMTP_*` codes explicit, so `SMTP_REJECTED`, `SMTP_AUTH_FAILED` and `SMTP_TLS_FAILED` are never treated as "unknown". They fall to rule 6.
- `wait(ms, signal)` is a `setTimeout` promise. When the optional `signal` fires, the timer is cleared and the promise rejects. It also rejects when `signal` is already aborted. The listener is added with `{ once: true }` and removed when the timer fires. What it rejects with is internal: the loop turns any rejection into `ABORTED`.

Add `specs/lib/retry_spec.js`, which unit-tests the module to 100 %:

- `backoffDelay`:
  - `500` gives `500`, `1000`, `2000`, `4000`, `8000` and `8000`;
  - `0` gives `0`;
  - `10000` gives `8000`.
- `shouldRetry`, with `retryAmbiguous` both on and off, for:
  - every row of the retry table;
  - every code in the never-retried list;
  - an unknown non-`SMTP_` code;
  - unknown `SMTP_*` on `502`, `503`, `504` and `500`;
  - a `NETWORK_ERROR` with `possiblySent` `true` and `false`.
- `wait`:
  - it resolves;
  - it rejects when the signal is already aborted;
  - it rejects when the signal fires while waiting, without waiting out the timer.

## Files to Change

- `clients/nodejs/lib/retry.js` (new): the retry decision, the backoff and the abortable wait.
- `clients/nodejs/specs/lib/retry_spec.js` (new): its unit spec.
