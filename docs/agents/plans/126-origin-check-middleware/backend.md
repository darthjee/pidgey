# Backend Plan: Origin check middleware

Main plan: [plan.md](plan.md)

## Shared contracts

Implement exactly the rules in [plan.md](plan.md#shared-contracts): checked methods, rejection, `Sec-Fetch-Site`, `Origin`/`Host` parsing, trusted origins and the two `ALLOWED_ORIGINS` failure lines.

## Steps

- [01 — Request::header() and ForbiddenException](backend/01-request-header-and-forbidden.md)
- [02 — TrustedOrigins](backend/02-trusted-origins.md)
- [03 — OriginCheck middleware and registration](backend/03-origin-check.md)
- [04 — ALLOWED_ORIGINS config validation](backend/04-config-validation.md)

## CI Checks

- `source/`: `composer lint` (CI job: `backend_checks`)
- `source/`: `composer tests`, or `composer coverage` in CI (CI job: `backend_tests`)
- Locally, both run with `make tests`.

## Notes

- **Construction:** `FrontController` builds middlewares with `new $class()`, with no arguments. `OriginCheck` therefore takes an optional `?TrustedOrigins` constructor argument and defaults to `TrustedOrigins::fromEnv()`. Do not change how `FrontController` builds middlewares in this issue.
- **Security review:** the `security` agent reviews before the PR is marked ready, focusing on parsing bypasses (`Origin: null`, user info, IPv6, uppercase hosts, missing `Host`) and on config values never leaking into failure lines.
- **Overlap with #127:** #127 also needs `Request::header()`. If #127 lands first, reuse its implementation instead of adding a second one.
- **CORS is out of scope:** see #139.
