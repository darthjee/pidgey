# Backend Plan: Auth endpoints: register and login

Main plan: [plan.md](plan.md)

## Overview
Implement the two public session-starting endpoints. The work is split into small domain services (`Registration`, `Login`) that the handlers call, so the tricky rules (the unique-index race, the constant-time unknown-user path, the status check after the password) are tested directly, without HTTP, and the handlers stay thin: validate, call the service, build the shared `201` response.

## Context
- Specs: `docs/agents/specs/api/endpoints/auth/session.md` (register, login), `docs/agents/specs/api/endpoints/auth.md` (guards, trimming, identifiers, password hashing), `docs/agents/specs/api/authentication/gate.md#shared-user-payload`, `docs/agents/specs/architecture/configuration.md` (`USER_APPROVAL_REQUIRED`).
- Already on `main`:
  - `Sessions::start(User): SessionTokens` (`Container::sessions()`), which opens **no** transaction, so register calls it inside its own `Transaction::run()` (transactions cannot be nested).
  - `SessionTokens::refreshToken()`, `accessToken()`, `UserPayload::fromUser()`, `AccessTokenCookie::set($jwt, $ttl)`, `SetCookie::applyTo(Response)`.
  - `Validator`/`FieldRules` (`trim`, `notEmpty`, `email`, `minLength`, `maxLength`, `maxBytes`, `noNulByte`), `ValidationFailedException` (400).
  - `UserRepository::create()`, `findByUsername()`, `findByEmail()`; `OrganizationRepository::create()`; `Transaction::run()`.
  - `UnauthorizedException`, `ForbiddenException` (message, code). There is **no** `409` exception yet.
  - `Router::post()`; route files are required from `source/config/routes.php`.
- Decisions agreed in the issue: on SQLSTATE `23000` from the user insert, roll back the whole transaction and **re-run the pre-check** to pick `USERNAME_TAKEN` or `EMAIL_TAKEN`; a hard-coded `DUMMY_DIGEST`; routes in a new `source/config/routes/auth.php`; backend only.

## Steps

- [01 — Building blocks: conflict exception, repository methods, config readers](backend/01-building-blocks.md)
- [02 — Shared session response and password helpers](backend/02-session-response-and-passwords.md)
- [03 — Registration service and register handler](backend/03-register.md)
- [04 — Login service and login handler](backend/04-login.md)
- [05 — Routes, route tests and log check](backend/05-routes-and-logs.md)

## CI Checks
- `source/`: `composer lint` (CI job: `backend_checks`)
- `source/`: `composer coverage` (CI job: `backend_tests`); locally `make tests` (runs `composer lint && composer tests` in the `tests` container).

## Notes
- **Never log secrets:** mark every password, digest and token parameter with `#[SensitiveParameter]`; the handlers and services never pass request bodies, passwords, digests or tokens to the logger.
- **Timing:** login always runs exactly one `password_verify()`, against the user's digest or `DUMMY_DIGEST`. The status check and the rehash happen only after a successful verify.
- **Status after password:** a `denied` user with a wrong password gets the same `401` as anyone else; `403 USER_DENIED` only with the right password. Neither path starts a session or sets a cookie.
- **The race:** the race between the pre-check and the insert is simulated by calling the transactional part (`Registration::create()`) directly with a username or email that is already taken, so the pre-check is skipped, like the stale-entity approach of #129. The test asserts the `409` and that no organization row was left behind.
- **Register is the documented enumeration exception** (`409`s reveal existence); login must stay enumeration-safe.
- **Password hashing cost:** `password_hash()` is slow (bcrypt cost 12). Tests that create many users should insert fixtures with a precomputed digest (`Fixtures::user()` already does) and only hash through the service where the test is about hashing.
- **No throttle** in v1 (spec, Brute force).
- **Security review:** the `security` agent reviews the change before the PR is marked ready (issue acceptance).
