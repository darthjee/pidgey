# Plan: Implement the pidgey-client retry policy

Issue: [248-implement-the-pidgey-client-retry-policy.md](../../issues/248-implement-the-pidgey-client-retry-policy.md)

## Overview

`send` wraps #247's single `#attempt` in a retry loop that follows the retry table in
`docs/agents/specs/node-client/errors-and-retry.md`. The retry decision, the backoff delay and
the abortable wait live in a new module, `lib/retry.js`, as pure functions with their own unit
spec. The loop is covered end to end by `specs/lib/RetryPolicy_spec.js` against `TestServer`.
`product-owner` then records the as-built state and the backoff-cap rule settled in the issue.

## Agents involved

- [nodejs](nodejs.md)
- [product-owner](product-owner.md)

## Shared contracts

- **New module** `clients/nodejs/lib/retry.js`. It is internal and not exported from `lib/index.js`. It provides:
  - `MAX_BACKOFF_MS = 8000`;
  - `backoffDelay(retryDelayMs, retry)`: `retry` is 1-based, and the result is `Math.min(retryDelayMs * 2 ** (retry - 1), MAX_BACKOFF_MS)`. The cap applies even to the first wait, so `retryDelayMs: 10000` waits 8000 ms (decided in the issue).
  - `shouldRetry(error, retryAmbiguous)`, which returns a boolean, checked in this order:
    1. **Never, whatever the options:** `INVALID_RESPONSE`, `ABORTED`, `CLIENT_CONFIGURATION`, `SMTP_PROTOCOL_ERROR`, `IDEMPOTENCY_KEY_IN_USE` and `IDEMPOTENCY_KEY_REUSED`.
    2. **An ambiguous attempt** (`error.possiblySent === true`) is retried only when `retryAmbiguous` is on. This invariant is what keeps the default client from ever causing a duplicate.
    3. **`NETWORK_ERROR`, `SMTP_CONNECTION_FAILED` and `SMTP_TEMPORARY_FAILURE`:** retried.
    4. **`SMTP_TIMEOUT`:** retried. Step 2 has already required `retryAmbiguous`, because this code is always ambiguous.
    5. **Unknown `SMTP_*`:** retried when `statusCode` is `503` or `504`. A `504` is ambiguous, so step 2 has already gated it. A `502` or any other status is not retried.
    6. **Anything else** (any other code or status): not retried.
  - `wait(ms, signal)`: resolves after `ms`. If `signal` fires, it rejects at once and clears its timer. It also rejects when `signal` is already aborted.
- **`possiblySent` across attempts:** the loop keeps `anyAmbiguous`, the OR of every failed attempt's `possiblySent`.
  - When it rejects with the last error, it sets `possiblySent = anyAmbiguous || error.possiblySent`.
  - An abort during the backoff rejects with a new `PidgeyError` with `code: 'ABORTED'` and `possiblySent: anyAmbiguous`.
- **Attempts:** at most `maxRetries + 1`, all with the same body and `Idempotency-Key`.
- **Spec paths:**
  - `specs/lib/retry_spec.js` (new) for the pure functions;
  - `specs/lib/RetryPolicy_spec.js` (new) for the loop, as the `testing.md` row lists.
- **Pack list:** `lib/retry.js` is added to `clients/nodejs/pack-files.txt`.
