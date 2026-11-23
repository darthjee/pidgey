# Nodejs Plan: Implement the pidgey-client retry policy

Main plan: [plan.md](plan.md)

## Shared contracts

This plan produces everything in the shared contracts of [plan.md](plan.md):

- `lib/retry.js`, with `MAX_BACKOFF_MS`, `backoffDelay`, `shouldRetry` and `wait`, in the decision order given there;
- the loop in `PidgeyClient#send`, with its `anyAmbiguous` rule;
- the two new specs;
- `lib/retry.js` added to the pack list.

## Steps

- [01 — Retry module](nodejs/01-retry-module.md)
- [02 — Retry loop in send](nodejs/02-retry-loop.md)
- [03 — README and pack list](nodejs/03-readme-and-pack-list.md)

## CI Checks

- `clients/`: `make clients-checks` (CI job: `clients_checks`, on Node 22.12.0 and the current image). This runs lint, the typecheck, `yarn coverage` at 100 % and the pack-list comparison.

## Notes

- Decide from `code`, `statusCode` and `possiblySent`, never from `smtp`.
- Gating on `possiblySent` (rule 2) instead of listing codes keeps the default client safe even when a server's envelope `statusCode` disagrees with the HTTP status. `possiblySent` is computed from the HTTP status in `response.js`.
- `PidgeyError#possiblySent` is a plain writable field, so the loop can set it on the last error before rethrowing.
- Every timer must be cleared, the backoff timer included, so a finished or aborted call leaves no open handle and Jasmine exits promptly.
