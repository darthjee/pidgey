# Infra Plan: Access token: JWT HS256 and `access_token` cookie

Main plan: [plan.md](plan.md)

## Shared contracts

- Development key `pidgey-development-only-insecure-secret-key-0000`; the web boot refuses an invalid configuration, so every environment that runs `FrontController::run()` needs a valid `SECRET_KEY` ([plan.md](plan.md#shared-contracts)).

## Implementation Steps

### Step 1 — Development key in docker compose
Add `SECRET_KEY=pidgey-development-only-insecure-secret-key-0000` to the committed root `.env` (read by every compose service through `env_file`). The `app` service runs with `PHP_ENV=development` and `tests` with `PHP_ENV=test`, so the key is allowed in both.

### Step 2 — Development key in CI
Add `SECRET_KEY: pidgey-development-only-insecure-secret-key-0000` to the `backend_tests` job's primary-container environment in `.circleci/config.yml` (it already sets `PHP_ENV: test`). Without it, `FrontControllerRunTest` gets a `500` once the boot refusal lands.

## Files to Change
- `.env` — the development `SECRET_KEY` line.
- `.circleci/config.yml` — `SECRET_KEY` in `backend_tests`.

## Notes
- Never put a real key in either file: production keys live only in the host's env file.
