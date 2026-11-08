# Product Owner Plan: Specs: email test infrastructure and frontend alignment

Main plan: [plan.md](plan.md)

## Shared contracts

Use the Mailpit instance table, the `TEST_MAILPIT_*` keys, the test environment keys, the
removal of `SMTP_HOST` / `SMTP_PORT` and the TLS fixture list exactly as in
[plan.md](plan.md#shared-contracts).

## Steps

- [01 — Mailpit instances, keys and TLS fixtures](product-owner/01-mailpit-and-tls.md)
- [02 — Fake SMTP server and required coverage](product-owner/02-fake-server-and-coverage.md)
- [03 — Configuration and docker specs](product-owner/03-configuration-and-docker.md)
- [04 — Frontend screens and API compatibility](product-owner/04-frontend-screens.md)
- [05 — Frontend test fixtures and definition of done](product-owner/05-frontend-testing.md)
- [06 — Close the implement-email open items](product-owner/06-close-open-items.md)

## Notes

- Specs only. Every decision is the issue's; do not reopen #151–#153 decisions.
- `implement-email/*.md` is temporary: put the durable rules in the permanent specs first,
  then mark the open items decided, linking to them.
