# Backend Plan: Access token: JWT HS256 and `access_token` cookie

Main plan: [plan.md](plan.md)

## Shared contracts

- Development key constant `ConfigValidator::DEVELOPMENT_SECRET_KEY = 'pidgey-development-only-insecure-secret-key-0000'`; the infra agent puts the same value in `.env` and the CircleCI `backend_tests` job.
- Boot refusal in `FrontController::run()`: failure names only, `500 INTERNAL_ERROR`.
- Claim formats, `verify()` returning `?AccessTokenClaims`, and the exact clear-cookie line: see [plan.md](plan.md#shared-contracts).

## Steps

- [01 — Development key and web boot refusal](backend/01-development-key-and-boot-refusal.md)
- [02 — Secret keys and JWT HS256](backend/02-secret-keys-and-jwt.md)
- [03 — SetCookie and the access-token cookie](backend/03-set-cookie.md)
- [04 — Request::cookie()](backend/04-request-cookie.md)

## CI Checks
- `source`: `composer lint` (CI job: `backend_checks`)
- `source`: `composer coverage` with `PHP_ENV=test` (CI job: `backend_tests`); locally `make tests`.

## Notes
- No JWT library: the runtime stays `php` + `monolog` (`architecture/dependencies.md`).
- Never log or return a key or a token, not even partially; test failure paths do not leak them in exception messages either.
- All comparisons of secrets (signatures) use `hash_equals()`.
- `security` agent review before the PR is marked ready.
- No handler or middleware uses any of this yet.
