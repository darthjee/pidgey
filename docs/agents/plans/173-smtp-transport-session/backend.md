# Backend Plan: SMTP transport session

Main plan: [plan.md](plan.md)

## Shared contracts

The backend produces every name in [plan.md](plan.md#shared-contracts) exactly as listed. It
relies on the #163 primitives, unchanged:

- `SmtpStream::open/localAddress/writeLine/readLine/upgradeToTls/close`;
- `ReplyParser::read(stream, budget)`, which throws `MalformedReply` or `StreamFailure`;
- `EhloExtensions::fromReply()->has()/authMechanisms()`;
- `Deadline::start(clock, s)` and `->within(s)`;
- `StreamFailure::kind()/reason()`;
- `SessionSettings`, `SecretString::reveal()`.

## Steps

- [01 — Value objects, enums and failure](backend/01-value-objects.md)
- [02 — The SMTP session](backend/02-smtp-transport.md)
- [03 — Integration and canary tests](backend/03-integration-tests.md)

## CI Checks

- `source/`: `make tests` (CI jobs: `backend_checks`, `backend_tests`). Integration tests need
  the three Mailpit instances (compose `tests` service / CircleCI `backend_tests`).

## Notes

- `Pidgey\Smtp\` must not import `Pidgey\Model\`, `Pidgey\Infra\Env`, a repository or the
  logger (`architecture/backend.md#layers`). Only `Pidgey\Infra\Clock` and `SecretString` are
  allowed.
- Do not redact or sanitize reply text, and do not log anything: that is #165.
- Never disable TLS verification in a test. `NoDisabledVerificationTest` already guards
  `lib/`; keep it green.
- Every new public class gets docblocks in the existing style (phpcs and slevomat rules).
