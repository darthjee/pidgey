# Backend Plan: Test email endpoint and rate limit

Main plan: [plan.md](plan.md)

## Shared contracts

Everything in [plan.md](plan.md#shared-contracts): the route, the `to` rules and their
order, the exact response strings, the `smtp_test_limits` columns, the configuration and the
operator log line.

## Steps

- [01 — Configuration and migration](backend/01-configuration-and-migration.md)
- [02 — Test-email limit and 429](backend/02-test-email-limit.md)
- [03 — Recipient, content and operator log](backend/03-recipient-content-and-log.md)
- [04 — Handler, route and tests](backend/04-handler-route-and-tests.md)

## CI Checks

- `source/`: `composer lint` (CI job `backend_checks`) and `composer coverage` after
  `composer db:prepare`, with Mailpit (CI job `backend_tests`).

## Notes

- Do not change `SendFlow`, `HostPolicy` or the transport; a gap found there becomes its
  own issue.
- `AccountEditLockoutRepository` is the reference for the upsert-then-lock pattern;
  `SmtpConnectionStateHandler` is the reference for the handler shape, `build()` and the
  transaction use.
- The `security` agent reviews the PR: the recipient restriction, no enumeration, the lease,
  and the canary password. It has no plan file.
