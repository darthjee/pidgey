# Backend Plan: SMTP connection endpoints: CRUD, activate and deactivate

Main plan: [plan.md](plan.md)

## Shared contracts

Produces everything listed in [plan.md#shared-contracts](plan.md#shared-contracts): the
routes, `PathId`, the validator additions, `SmtpConnectionRules` messages, `OperatorLog`, the
lock-holder script and migration `0010`. Names and messages are exact.

## Steps

- [01 — Validator rules](backend/01-validator-rules.md)
- [02 — Migration and repositories](backend/02-migration-and-repositories.md)
- [03 — Connection rules, payload, path id and operator log](backend/03-rules-payload-log.md)
- [04 — Handlers and routes](backend/04-handlers-and-routes.md)
- [05 — Concurrency tests and lock holder](backend/05-concurrency.md)
- [06 — Endpoint tests](backend/06-endpoint-tests.md)

## CI Checks

- `source`: `make tests` (CI job: `backend_tests`) and `composer lint` (phpcs + phpmd).

## Notes

- `PasswordCipher::encrypt()` takes a plain `string` (`#[SensitiveParameter]`); keep the
  request password out of exception messages, logs and `__debugInfo` dumps.
- `SmtpConnection` already carries `encryptedPassword`; the payload must never serialize it.
- Rows stored before the rules (credentials with `none`, non-LDH host) are not migrated:
  any `PATCH` fails on the merged state until fixed; list / read / activate / delete work.
- The `409 NO_SMTP_CONNECTION` follow-up of delete / deactivate belongs to #165.
