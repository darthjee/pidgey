# Retry loop in send

In `clients/nodejs/lib/PidgeyClient.js`, `send` keeps its argument validation and its pre-abort check. It then runs a loop instead of a single `#attempt`:

```js
const key = idempotencyKey ?? crypto.randomUUID();
let anyAmbiguous = false;
for (let attempt = 0; ; attempt += 1) {
  try {
    return await this.#attempt(body, key, signal);
  } catch (error) {
    anyAmbiguous ||= error.possiblySent;
    if (attempt >= maxRetries || !shouldRetry(error, retryAmbiguous)) {
      error.possiblySent = anyAmbiguous;
      throw error;
    }
  }
  try {
    await wait(backoffDelay(retryDelayMs, attempt + 1), signal);
  } catch {
    throw new PidgeyError('The call was aborted.', { code: 'ABORTED', possiblySent: anyAmbiguous });
  }
}
```

The shape may differ, for example a private `#run` method. The behavior must match:

- the key is generated once per call;
- `#attempt` is unchanged;
- an `ABORTED` from an attempt is never retried;
- the last error is the one rejected.

Update the JSDoc of `send` and of the `maxRetries`, `retryDelayMs` and `retryAmbiguous` constructor options. Describe the retry behavior, the 8000 ms cap and the duplicate risk of `retryAmbiguous`.

**Existing specs:** `PidgeyClientSend_spec.js` and `PidgeyClientSendAbort_spec.js` were written for one attempt. Where a case asserts the outcome of a single failed attempt, construct its client with `maxRetries: 0`, so the request counts and timings stay the same.

Add `clients/nodejs/specs/lib/RetryPolicy_spec.js`, which covers the `testing.md` row against `TestServer`. Use a small `retryDelayMs` (`1`) so the suite stays fast. It covers:

- one case per retry-table row, as a scripted reply sequence. For example, `SMTP_CONNECTION_FAILED` then `200` resolves after two requests, and `SMTP_REJECTED` rejects after one;
- unknown `SMTP_*` on `502`, `503` and `504`;
- the never-retried replies: `400`, `401`, `403`, `409`, `422`, `429`, `500`, a 3xx and a non-JSON `200`;
- network errors: a refused connection, retried, from a server that was stopped; a destroyed socket and a timeout, retried only with `retryAmbiguous`;
- the attempt count for `maxRetries` `0`, `1` and `2`. When retries run out, the last error surfaces, with a different code on each attempt;
- the backoff growing and capped, observed by timing the gaps between requests with `retryDelayMs: 20` and `maxRetries: 2`, and without asserting exact times;
- `retryAmbiguous` off and on;
- `possiblySent`:
  - for each code;
  - the any-attempt rule: an ambiguous timeout, then an unambiguous `SMTP_CONNECTION_FAILED` that runs out of retries, gives `true`;
- `ABORTED`:
  - before any request: `false`;
  - in flight, including while reading the body: `true`;
  - during the backoff after only unambiguous failures: `false`, with no further request;
  - during the backoff after an earlier ambiguous attempt: `true`;
  - while reading the body of an already-received `400`, `401`, `403` or `409`: `true`;
- the same `Idempotency-Key` on every request of one call (the caller's key and a generated one), and a new key for the next call;
- `409 IDEMPOTENCY_KEY_IN_USE` (`possiblySent: true`) and `422 IDEMPOTENCY_KEY_REUSED` (`possiblySent: false`), each never retried, also with `retryAmbiguous: true`.

## Files to Change

- `clients/nodejs/lib/PidgeyClient.js`: the retry loop in `send`, and the JSDoc.
- `clients/nodejs/specs/lib/PidgeyClientSend_spec.js`: `maxRetries: 0` where a case asserts one attempt.
- `clients/nodejs/specs/lib/PidgeyClientSendAbort_spec.js`: the same.
- `clients/nodejs/specs/lib/RetryPolicy_spec.js` (new): the loop spec.
