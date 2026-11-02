# Backend Plan: Sessions service: refresh tokens, rotation, replay detection

Issue: [129-sessions-service-refresh-tokens-rotation-replay-detection.md](../../issues/129-sessions-service-refresh-tokens-rotation-replay-detection.md)

## Overview
Build `Pidgey\Auth\Sessions`, plus a small `SessionRotation` collaborator for the atomic rotate step, and the conditional-revoke methods on `RefreshTokenRepository` that they need. `Container::sessions()` exposes the service. It is consumed by #131 (register and login) and #132 (refresh, logoff and status); neither is part of this issue.

## Context
- Specs:
  - `docs/agents/specs/api/authentication/sessions.md` (refresh token, sessions, rotation, revocation reasons, replay detection, clock);
  - the refresh table in `docs/agents/specs/api/endpoints/auth/session.md` (checks 1–6, the logoff and status rules);
  - `docs/agents/specs/organizations/data-model/refresh-tokens.md`.
- Already on `main`:
  - `RefreshToken`, `RevokedReason`, and `RefreshTokenRepository` (`create`, `findById`, `findByTokenHash`);
  - `UserRepository::findById`, `UserStatus`;
  - `Transaction::run()` (not nestable);
  - `AccessToken::issue(int $userId, string $sessionId, string $username, bool $isAdmin)`;
  - `WallClock`, `Container` with lazy getters;
  - `ConfigValidator::DEFAULT_ACCESS_TOKEN_TTL` (public), `DEFAULT_REFRESH_TOKEN_TTL` (private string);
  - test support: `RepositoryTestCase`, `Fixtures::refreshToken()` and `Fixtures::user()`.
- Repositories are `final`, so the lost-race test works on a **stale `RefreshToken` entity**. The row is revoked directly in the database after the entity was read, then the rotate step is called with that entity. This is why rotation is its own collaborator, `SessionRotation`, with a public `rotate()`.

## Steps

- [01 — Conditional revoke methods on the repository](backend/01-repository-revoke-methods.md)
- [02 — Token values, the TTL default and `SessionTokens`](backend/02-token-values-and-config.md)
- [03 — `Sessions::start()` and container wiring](backend/03-session-start.md)
- [04 — Rotation and `Sessions::refresh()`](backend/04-rotation-and-refresh.md)
- [05 — Logout, bulk revoke and status support](backend/05-logout-revoke-and-status.md)

## CI Checks
- `source/`: `make tests` locally, which runs `composer lint && composer tests` (CI jobs: `backend_checks` runs `composer lint`, `backend_tests` runs `composer coverage`).

## Notes
- **Secrets:**
  - plain refresh tokens and JWTs are never logged and never put in exception messages;
  - mark every parameter that carries one with `#[SensitiveParameter]`;
  - `SessionTokens` must not expose its values through `__toString`, `__debugInfo` or `var_export`; add a `__debugInfo` that redacts them.
- **Clock:**
  - read `now` from `WallClock` once per operation and bind it as a parameter; SQL never calls `NOW()`;
  - store times through `Sql::dateTime()` (UTC).
- **Replay and denied revocations must be committed** even though the operation then fails. They run outside the rotation transaction, as their own statements.
- **No new Composer dependency.** UUID v4 comes from `random_bytes(16)` with the version and variant bits set.
- `start()` must **not** open a transaction, because register (#131) calls it inside its own `Transaction::run()`, and transactions are not nested.
- A uniform failure is represented as `null`. Callers must not be able to tell the branches apart, and nothing returns or logs the reason.
- **Security review:** the `security` agent reviews the change before the PR is marked ready.
