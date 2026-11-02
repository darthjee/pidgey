# Backend Plan: Gate middleware and shared `user` payload

Main plan: [plan.md](plan.md)

## Overview
Implement the cookie-surface gate per `docs/agents/specs/api/authentication/gate.md`, plus the plumbing it needs per these specs:
- `docs/agents/specs/request-handler/handlers.md`;
- `middlewares.md`;
- `request-response/processing-request.md`;
- `request-response/errors.md`.

No spec changes are needed: the specs already describe everything below. If the code disagrees with a spec, the spec wins. Report any real spec gap in the PR description; do not invent behaviour.

## Context
- #128 (merged) provides:
  - `Pidgey\Auth\AccessToken` (`__construct(SecretKeys, int $ttl, WallClock)`, `verify(string): ?AccessTokenClaims`);
  - `AccessTokenClaims` (`userId`, `sessionId`, `issuedAt`, `expiresAt`);
  - `SecretKeys::fromEnv()`;
  - `Request::cookie()`.
- `FrontController::handle()` currently does `new ($match->handlerClass)()` and `new $class()` for middlewares. There is no container in the dispatch path.
- `Container` already has lazy repository getters, including `userRepository()` and `pdo()`.
- `Router` has no way to list its routes. The deny-by-default test needs one.

## Steps

- [01 — build(Container), HandlerFactory and per-request Container](backend/01-handler-factory-and-container.md)
- [02 — ProcessingRequest attachments and UnauthorizedException](backend/02-attachments-and-unauthorized.md)
- [03 — Caller, AuthenticatedUser and the session lookup query](backend/03-caller-and-session-lookup.md)
- [04 — Gate and AdminCheck middlewares](backend/04-gate-and-admin-check.md)
- [05 — Shared user payload](backend/05-user-payload.md)
- [06 — Deny-by-default and factory integration tests](backend/06-route-integration-tests.md)

## CI Checks
- `source/`: `composer lint` (CI job: `backend_checks`)
- `source/`: `composer coverage` (CI job: `backend_tests`); locally `make tests`

## Notes
- **Never log** the cookie value, the JWT, the claims or the reason a token was rejected. The gate gives callers no detail.
- **Uniform `401`:** every gate failure throws the same `UnauthorizedException('Invalid or expired access token')`. Use one constant or factory, so the message cannot drift. Tests compare the formatted responses byte for byte, minus `timestamp`.
- **Database errors are not caught** by the gate. A `PDOException` propagates and the `ErrorFormatter` answers `500`.
- **Laziness:** no container getter may open PDO or read secrets unless it is called. `/health.json` and the static routes must not open a connection, and must not need `SECRET_KEY` beyond boot validation.
- Hooks never catch exceptions. Guards throw; they never call `respond()`.
- `Pidgey\Http\` must not depend on any `Pidgey\Auth\` class.
- No new Composer dependency.
- A `security` agent review is required before the PR is marked ready.
- No production route uses `Gate` or `AdminCheck` yet. #131–#133 add the auth routes.
