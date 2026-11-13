# Backend Plan: Send flow: MIME, host policy and failure mapping

Main plan: [plan.md](plan.md)

## Shared contracts

All of [plan.md#shared-contracts](plan.md#shared-contracts): backend produces every class
there. It relies on #173 for the `Transport` interface, `SmtpFailure` (failure code, step,
optional reply, optional TLS reason) and `FailureCode`, and on #163 for `Reply::text()` and
`SecretString`.

## Steps

- [01 — Message and MIME builder](backend/01-mime-builder.md)
- [02 — Host policy](backend/02-host-policy.md)
- [03 — Reply text, outcome and send flow](backend/03-send-flow.md)
- [04 — SMTP failure exception](backend/04-failure-exception.md)
- [05 — Send flow integration and canary tests](backend/05-integration-tests.md)

## CI Checks

- `source/`: `make tests` (CI job: `backend_tests`), `composer lint`.

## Notes

- Do not start before #173 is merged; adapt to its final names.
- Mark every secret-carrying parameter `#[SensitiveParameter]`.
- Mirror the existing docblock style (phpcs and slevomat).
