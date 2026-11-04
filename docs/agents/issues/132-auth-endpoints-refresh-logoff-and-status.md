# Issue: Auth endpoints: refresh, logoff and status

## Description
Part of epic #120 (auth API). Add the last three public session endpoints: `POST /auth/refresh.json`, `DELETE /auth/logoff.json` and `POST /auth/status.json`, following `docs/agents/specs/api/endpoints/auth/session.md`. The frontend already calls all three (`ApiClient.js` refresh flow, `AuthStatus.js`, `AccountsClient.js`).

Depends on #131 (register/login) and #129 (sessions service), both merged.

## Problem
The frontend cannot keep, inspect or end a session: an expired access token cannot be renewed, the mount-time "am I logged in?" check has no backend, and logout leaves the refresh token active and the cookie set.

These building blocks are already on `main`:
- `Sessions::refresh()` (rotation, replay detection, revoke of a denied user's token, lost race, all returning null);
- `Sessions::logout()`, `findActive()` and `revoke()`;
- `SessionResponse::created()` (201 body `{user, refreshToken}` plus the cookie);
- `AccessTokenCookie::clear()`;
- `Router::delete()` and JSON body parsing on `DELETE`;
- `Container::sessions()` and `accessToken()`.

## Expected Behavior
**`POST /auth/refresh.json`** (public). Body `{ "refreshToken": "…" }`, a non-empty string.
- Missing or empty → `400 VALIDATION_FAILED`.
- Success → `201` with `{ user, refreshToken }` (the new token) and a new `access_token` cookie (same `sid`).
- Every failure of the spec's refresh table (unknown or expired, replay of a rotated token, other revocation, missing user, denied user, lost rotation race) → `401 UNAUTHORIZED` with the same message, `Invalid or expired refresh token`, and the side effects the spec defines (replay revokes every active token of the user with `replay_detected`; denied revokes the presented token with `admin_denied`).

**`DELETE /auth/logoff.json`** (public). Body `{ "refreshToken": "…" }`, a non-empty string.
- Missing or empty → `400 VALIDATION_FAILED`.
- Anything else → `204 No Content`, empty body, and the `access_token` cookie cleared (`Max-Age=0`, past `Expires`).
- The token is looked up whatever its state, and every active token of its session is revoked with `logout`; the user's other sessions are untouched.
- An unknown token is a silent no-op that still answers `204` and clears the cookie. Same behaviour for every user status.

**`POST /auth/status.json`** (public, passive). Body `{ "refreshToken": "…" }`, a non-empty string.
- Missing or empty → `400 VALIDATION_FAILED`; otherwise always `201`.
- Active token, approved → `{ "loggedIn": true, "isAdmin": <from DB> }`.
- Active token, pending → `{ "loggedIn": false, "isAdmin": false, "status": "pending" }`, session kept.
- Active token, denied → the anonymous body, byte for byte, and the presented token revoked with `admin_denied`.
- Unknown, revoked or expired token, or missing user → `{ "loggedIn": false, "isAdmin": false }`.
- Never rotates, never runs replay detection, never sets or clears the cookie.

All three routes are ungated (no `Gate`) and go through the global `OriginCheck`. Request bodies and tokens are never logged.

## Solution
- Add `RefreshHandler`, `LogoffHandler` and `StatusHandler` under `Pidgey\Http\Handler`, each validating `refreshToken` with `Validator` (string, not empty, never trimmed), then delegating to `Container::sessions()`:
  - refresh: `Sessions::refresh()`; null → `UnauthorizedException` with the fixed message; otherwise `SessionResponse::created($tokens, $accessToken->ttl())`;
  - logoff: `Sessions::logout()`, then an empty `204` response with `AccessTokenCookie::clear()` applied;
  - status: `Sessions::findActive()` plus the status mapping above, calling `Sessions::revoke($token, RevokedReason::AdminDenied)` for a denied user.
- Register the routes in `source/config/routes/auth.php` (`post`, `delete`, `post`) and add them to the `RoutesSecurityTest::publicRoutes()` allowlist and the handler-builds-from-container test.
- Integration tests per handler for success and every row of the spec tables, including replay of a rotated token (all user tokens revoked), the denied cases, logoff of an expired/rotated token, logoff leaving other sessions active, the status answers being byte-identical for denied and unknown, and status never touching the cookie.
- `security` agent review before the PR is marked ready.

## Benefits
Completes the session lifecycle the frontend already implements: silent renewal of expired access tokens, a passive login check on mount, and a real logout that revokes the server-side session.
