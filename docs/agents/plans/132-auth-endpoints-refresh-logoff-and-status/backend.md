# Plan: Auth endpoints: refresh, logoff and status

Issue: [132-auth-endpoints-refresh-logoff-and-status.md](../../issues/132-auth-endpoints-refresh-logoff-and-status.md)

## Overview
Three handlers under `Pidgey\Http\Handler`, each validating `refreshToken` and delegating to `Container::sessions()`. No new service logic: rotation, replay detection, the denied-user revoke, logout and the passive lookup already live in `Pidgey\Auth\Sessions` (#129). The routes go in `source/config/routes/auth.php` next to register and login (#131).

## Context
- Spec: `docs/agents/specs/api/endpoints/auth/session.md` (refresh, logoff, status sections). Tokens and rotation: `docs/agents/specs/api/authentication/sessions.md`. The cookie: `docs/agents/specs/api/authentication/access-token.md#cookie`.
- Already on `main`:
  - `Sessions::refresh(string): ?SessionTokens`, which returns null on every failure and has already committed the replay/denied side effects;
  - `Sessions::logout(string): void`;
  - `Sessions::findActive(string): ?ActiveSession` (`->token`, `->user`) and `Sessions::revoke(RefreshToken, RevokedReason)`;
  - `SessionResponse::created(SessionTokens, int $accessTtl)`, the 201 body `{user, refreshToken}` plus the cookie;
  - `AccessTokenCookie::clear()` and `SetCookie::applyTo(Response)`;
  - `AccessToken::ttl()`;
  - `Router::delete()`, and `Request::json()` reads bodies on every method;
  - `UnauthorizedException(message, code)`, `Validator`, `Response(body, status)`;
  - the test base `Pidgey\Tests\Support\SessionEndpointTestCase` (POST only today) and `AuthLogTest`.
- The frontend already calls all three: `frontend/assets/js/client/ApiClient.js` (refresh reads `data.refreshToken`), `AuthStatus.js` (reads `loggedIn`, `isAdmin`, `status`), and `AccountsClient.js` (`logout`).

## Steps

- [01 — Test support for the session endpoints](backend/01-test-support.md)
- [02 — `POST /auth/refresh.json`](backend/02-refresh.md)
- [03 — `DELETE /auth/logoff.json`](backend/03-logoff.md)
- [04 — `POST /auth/status.json`](backend/04-status.md)
- [05 — Routes, allowlist and log checks](backend/05-routes-and-logs.md)

## CI Checks
- `source/`: `make tests` locally, which covers `composer lint` (CI job: `backend_checks`) and `composer coverage` (CI job: `backend_tests`, MySQL plus `SECRET_KEY`).

## Notes
- **One `401` message.** Every refresh failure is `new UnauthorizedException('Invalid or expired refresh token')` with code `UNAUTHORIZED`, defined once as a handler constant. The handler never branches on why `refresh()` returned null.
- **`refreshToken` is never trimmed:** `string('refreshToken')->notEmpty()` with no `->trim()`. Use the same validation in all three handlers. A small shared helper (e.g. a private static in a trait or a `RefreshTokenField` helper) is fine, but must not hide the rule.
- **Status is passive.** `StatusHandler` must not call `refresh()`, must not set or clear the cookie, and must not take `AccessToken`. The denied answer must be byte-identical to the anonymous one, so build both from the same constant or array.
- **Logoff answers `204` whatever happens** after validation, cookie included. Use `new Response('', 204)` and apply `AccessTokenCookie::clear()`. Do not use `JsonResponse`, so no body and no JSON content type are sent.
- **Mark every plain token parameter `#[SensitiveParameter]`.** Never put the token or the request body in a log line or exception message.
- **Transactions:** `Sessions::refresh()` opens its own transaction, so handlers must not wrap it in `Transaction::run()`.
- **Security review:** run the `security` agent on the diff before the PR is marked ready (issue acceptance).
