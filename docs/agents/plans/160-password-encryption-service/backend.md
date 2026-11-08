# Backend Plan: Password encryption service

Main plan: [plan.md](plan.md)

## Shared contracts

- Produce `ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY =
  'pidgey-development-only-insecure-smtp-key-0000000'`; infra sets the same value in `.env`
  and CircleCI.
- Produce the failure names listed in [plan.md](plan.md#shared-contracts), exactly.
- Produce the cipher contract in [plan.md](plan.md#shared-contracts); later issues depend on
  its signatures.
- Rely on infra for `SMTP_ENCRYPTION_KEY` in CircleCI `backend_tests`; until then, the
  container and boot tests need it set in the environment.

## Steps

- [01 — Configuration rules and sodium check](backend/01-configuration-rules.md)
- [02 — Key set and cipher](backend/02-key-set-and-cipher.md)
- [03 — Migration 0009](backend/03-migration-0009.md)
- [04 — Rotation CLI](backend/04-rotation-cli.md)

## CI Checks

- `source`: `composer lint` (CI job: `backend_lint` / "Run Lint")
- `source`: `composer db:prepare && composer coverage` (CI job: `backend_tests`)

## Notes

- Follow `Pidgey\Auth\SecretKeys` for the key set (`#[SensitiveParameter]`, `__debugInfo`
  redaction, `fromEnv()`).
- `monolog/monolog` is already required and `Pidgey\Infra\Clock` already exists; this issue
  needs neither (nothing here logs).
- Nothing outputs a key, a password or stored bytes: failure lines, exception messages,
  CLI output and debug dumps are all checked by tests.
- Tick the matching items of `docs/agents/specs/implement-email/encryption.md` (product-owner
  step) in the same PR.
