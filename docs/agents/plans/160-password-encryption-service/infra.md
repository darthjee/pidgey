# Infra Plan: Password encryption service

Main plan: [plan.md](plan.md)

## Shared contracts

- Set `SMTP_ENCRYPTION_KEY=pidgey-development-only-insecure-smtp-key-0000000` (the value of
  `ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY`) wherever the app boots in development
  or tests.

## Implementation Steps

### Step 1 — Development and CI environment

- Root `.env`: add `SMTP_ENCRYPTION_KEY=pidgey-development-only-insecure-smtp-key-0000000`
  next to `SECRET_KEY` (compose `app` and `tests` read it through `env_file`).
- `.circleci/config.yml`, `backend_tests` primary container `environment`: add
  `SMTP_ENCRYPTION_KEY` with the same value next to `SECRET_KEY`. Do not touch the
  `SMTP_HOST` / `SMTP_PORT` lines or Mailpit (that is #161).
- Check that the PHP images (`php:8.4-apache` base, the CircleCI base image) load `sodium`
  (`php -m | grep sodium`); it is bundled with the official images, so no change is expected.
  Record the result in the PR.

### Step 2 — Release note

In the PR description, state that the operator must add `SMTP_ENCRYPTION_KEY` to the server
`.env` before merging (see `docs/agents/deployment/operations.md`, "Add
`SMTP_ENCRYPTION_KEY` before the first release that validates it"), and that web boot and
`bin/check-config` now require it.

## Files to Change

- `.env` — development `SMTP_ENCRYPTION_KEY`.
- `.circleci/config.yml` — `SMTP_ENCRYPTION_KEY` in `backend_tests`.

## Notes

- The deployment docs already describe the key (`deployment/server.md`, `operations.md`); no
  doc change expected.
