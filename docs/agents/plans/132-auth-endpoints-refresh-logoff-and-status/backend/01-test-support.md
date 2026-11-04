# Test support for the session endpoints
Extend `SessionEndpointTestCase` so refresh, logoff and status tests can send any method and set up sessions in any state, without each test re-implementing it.

- Generalize `post()` into `send(Router $router, string $method, string $path, array|string $body, array $headers = []): Response`, and keep `post()` as a thin wrapper so the register and login tests stay unchanged. Add `delete()` as a wrapper too. Keep the logger capture already in `post()`, so the log assertions of step 05 work for every method.
- Add a helper that creates a user with a given `UserStatus` (and `isAdmin`), reusing the existing fixtures or repository calls the login tests use, and another that starts a session for that user through `$this->container()->sessions()->start($user)` and returns the `SessionTokens`. Tests then get a real plain refresh token without going through login.
- Add a helper to read one `refresh_tokens` row by `session_uuid` or token hash (or reuse `row()`), so tests can assert `revoked_at` and `revoked_reason`.
- To get an expired token, either insert it with a past `expires_at` through `RefreshTokenRepository::create`, or build the container with a fixed `WallClock` moved past the TTL (`Container` already accepts a `WallClock`). Pick whichever the #129 `Sessions` integration tests already use, for consistency.

## Files to Change
- `source/tests/Support/SessionEndpointTestCase.php`: `send()`/`delete()`, user and session helpers, token-row lookup.
