# Issue: Implement the pidgey-client retry policy

## Description

- Parent epic: #219 (wave 2, created by #228).
- Specs:
  - `docs/agents/specs/node-client/errors-and-retry.md`: sections Possibly sent, Retry policy, Retry table, `retryAmbiguous`, Backoff, Forward compatibility;
  - `docs/agents/specs/node-client/library.md`: sections Abort, Timeout, Worst-case duration;
  - `docs/agents/specs/node-client/testing.md`: the `specs/lib/RetryPolicy_spec.js` row.
- Owners: `nodejs` (library, specs, README), `product-owner` (as-built docs).
- Depends on #247, which is merged (48bfa94, PR #260). Blocks #251.
- Today, in `clients/nodejs/`:
  - `send` validates its arguments, then makes exactly one attempt through the private `#attempt(body, key, signal)`. That method has no state, so a loop can call it again with the same body and key.
  - Every failure is already a classified `PidgeyError`, and its `possiblySent` marks the attempt as ambiguous or not: network errors by `cause` code, the timeout, `SMTP_TIMEOUT`, `SMTP_PROTOCOL_ERROR`, any `SMTP_*` on `504`, and `INVALID_RESPONSE` on a 2xx.
  - `maxRetries`, `retryDelayMs` and `retryAmbiguous` are validated and stored, but not used yet. The README and `docs/agents/clients/nodejs.md` say so.

## Problem

`send` reports failures, but the epic's main promise is not built yet: a retry decision that never re-sends a message the server may have accepted. A transient failure (`SMTP_CONNECTION_FAILED`, `SMTP_TEMPORARY_FAILURE`, a refused connection) fails the call on its first attempt, although retrying it is safe.

## Expected Behavior

- Each row of the retry table behaves as specified. The default client (`retryAmbiguous: false`) never causes a duplicate.
- `maxRetries` allows at most `maxRetries + 1` attempts, so `0` means exactly one.
- The retries of a `send` reuse its `Idempotency-Key`. The next `send` gets a new one.
- When retries are exhausted, the **last** error is rejected. Its `possiblySent` is `true` if **any** attempt was ambiguous.
- The backoff starts at `retryDelayMs` and doubles each time, capped at 8000 ms. The caller's `AbortSignal` cancels it, and the call rejects at once with `ABORTED`. That error's `possiblySent` is `true` only if an earlier attempt was ambiguous.
- Unknown `SMTP_*` codes follow the status rows (`503` retried, `504` only with `retryAmbiguous`, `502` never). Any other unknown code is surfaced as received and never retried.
- `/v1` sends no `Retry-After`, so the client never reads one.
- Coverage stays at 100 %.

### Testing

`specs/lib/RetryPolicy_spec.js`, as listed in `testing.md`, runs against the in-process `TestServer`. It checks:

- one case per retry-table row;
- the never-retried statuses;
- the attempt count for `maxRetries` `0`, `1` and `2`;
- backoff growing and capped;
- the last error surfacing;
- `retryAmbiguous` off and on;
- `possiblySent` for every code, including the any-attempt rule;
- every `ABORTED` case, including an abort during the backoff;
- the key reused across retries and new for the next call;
- `409 IDEMPOTENCY_KEY_IN_USE` and `422 IDEMPOTENCY_KEY_REUSED` never retried, also with `retryAmbiguous: true`.

Backoff timing is checked without slowing the suite: the delays come from a pure, unit-tested function, and the loop specs use a small `retryDelayMs`.

## Solution

1. **Retry decision** (owner `nodejs`): a pure function from a `PidgeyError` and `retryAmbiguous` to retry or not.
   - It uses `code` and the status, never the raw `smtp` reply.
   - It follows the retry table exactly: unambiguous `NETWORK_ERROR`, `SMTP_CONNECTION_FAILED`, `SMTP_TEMPORARY_FAILURE` and unknown `SMTP_*` on `503` are retried. Ambiguous `NETWORK_ERROR`, `SMTP_TIMEOUT` and unknown `SMTP_*` on `504` are retried only with `retryAmbiguous`. Everything else is never retried.
2. **Backoff:** a pure function that gives the delay before retry *n*: `min(retryDelayMs × 2^(n−1), 8000)`.
   - The cap applies even to the first wait, so a `retryDelayMs` above 8000 waits 8000 ms. This is recorded in the option's JSDoc, the README and the spec's Backoff section.
3. **Loop in `send`:**
   - It calls `#attempt` up to `maxRetries + 1` times, with the same body and key.
   - Between attempts, it waits out the backoff with a timer that the caller's signal cancels.
   - It tracks whether any attempt was ambiguous and sets the final error's `possiblySent` from that.
4. **README** (owner `nodejs`): replace "not implemented yet" with the retry behavior.
   - The defaults, the backoff and the duplicate risk of `retryAmbiguous`.
   - The worst-case duration (about 91.5 s with the defaults), and `AbortSignal.timeout(ms)` to bound a whole call.
5. **As-built docs** (owner `product-owner`):
   - `docs/agents/clients/nodejs.md`: drop the "not built yet" notes and describe the retry loop and its specs;
   - `testing.md`: drop "Comes with #248" from the `RetryPolicy_spec.js` row;
   - `errors-and-retry.md`, section Backoff, and the `retryDelayMs` row in `library.md`: a base above 8000 ms is capped too.

Out of scope:
- reading `Retry-After`;
- an overall deadline option;
- jitter (the spec's backoff is plain exponential);
- the CLI (#251).

## Benefits

- Transient failures recover without help from the caller.
- The default never re-sends a message that may have been delivered.
- Callers who prefer an occasional duplicate over a lost send can opt in with `retryAmbiguous`.
- #251 (the CLI) builds on a finished library.
