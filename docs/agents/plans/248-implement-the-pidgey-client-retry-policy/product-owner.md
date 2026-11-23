# Product-owner Plan: Implement the pidgey-client retry policy

Main plan: [plan.md](plan.md)

## Shared contracts

This plan relies on `nodejs` producing:

- `lib/retry.js` (`backoffDelay`, `shouldRetry`, `wait`), with `MAX_BACKOFF_MS = 8000` capping every wait, including a base above 8000 ms;
- the loop in `send`: at most `maxRetries + 1` attempts with one key, the last error rejected with the any-attempt `possiblySent`, and `ABORTED` during the backoff;
- the specs `specs/lib/retry_spec.js` and `specs/lib/RetryPolicy_spec.js`.

## Implementation Steps

### Step 1 — As-built client doc

Update `docs/agents/clients/nodejs.md`:

- drop "Retries are not built yet (#248)" and the "unused until #248" notes on the three options;
- describe the retry loop and `lib/retry.js`: the decision order, including the rule that an ambiguous attempt needs `retryAmbiguous`, plus the backoff and the abort during the backoff;
- list the two new specs.

Describe what was built, checked against the merged code.

### Step 2 — Spec updates

- `docs/agents/specs/node-client/errors-and-retry.md`, section Backoff: the 8000 ms cap applies to every wait, so a `retryDelayMs` above 8000 waits 8000 ms (decided in #248).
- `docs/agents/specs/node-client/library.md`: the same note on the `retryDelayMs` row of the options table.
- `docs/agents/specs/node-client/testing.md`:
  - drop "**Comes with #248.**" from the `specs/lib/RetryPolicy_spec.js` row;
  - add a `specs/lib/retry_spec.js` row for the decision, the backoff and the wait.

## Files to Change

- `docs/agents/clients/nodejs.md`: the as-built retry policy.
- `docs/agents/specs/node-client/errors-and-retry.md`: the backoff cap rule.
- `docs/agents/specs/node-client/library.md`: the `retryDelayMs` row.
- `docs/agents/specs/node-client/testing.md`: the spec rows.

## Notes

- Do this after `nodejs`, so the doc matches the merged code.
