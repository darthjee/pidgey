# Gate and AdminCheck middlewares

**`Pidgey\Auth\Gate`** extends `BaseMiddleware`.
- `__construct(AccessToken $tokens, UserRepository $users, WallClock $clock)`.
- `build(Container $c)` passes `$c->accessToken()`, `$c->userRepository()` and `$c->wallClock()`.
- `processRequest()`:
  1. `$jwt = $request->request()->cookie('access_token')`. If it is `null` or `''`, fail.
  2. `$claims = $tokens->verify($jwt)`. If it is `null`, fail.
  3. `$user = $users->findApprovedBySession($claims->userId, $claims->sessionId, $clock->now())`. If it is `null`, fail.
  4. `$request->attach(new AuthenticatedUser($user->id, $claims->sessionId, $user->username, $user->isAdmin, $user->organizationId))`.
- "Fail" means throwing the one uniform `UnauthorizedException`:
  - code `UNAUTHORIZED`, message `Invalid or expired access token`;
  - built in one private static helper or a constant, so every path is identical.
- It never reads `Authorization`.
- It catches nothing: a database exception propagates and becomes `500`.

**`Pidgey\Auth\AdminCheck`** extends `BaseMiddleware`.
- `processRequest()`: `$user = $request->require(AuthenticatedUser::class)`. If `!$user->isAdmin`, throw `ForbiddenException` with the message `Admin access required` and code `FORBIDDEN`, matching `gate.md#admin-check`.

Check the `ForbiddenException` constructor and the default code it emits, so the `403` body matches the spec exactly.

Tests:
- `GateTest` (unit) uses a real `AccessToken` with test keys and a fake clock, a fake or stub `UserRepository`, and a real `ProcessingRequest` built from `Request` overrides with a `cookies` option. Since `UserRepository` is `final`, either use the integration test database or introduce a small interface; prefer the integration database to avoid a new abstraction.
- `GateTest` cases:
  - a valid caller attaches `AuthenticatedUser` with `username` and `isAdmin` **from the database**, not from the token's claims (issue a token whose claims differ from the database row);
  - a token signed with a previous key still passes.
- Failures, each formatted through `ErrorFormatter` and asserted **identical** (status, headers and body apart from `timestamp`, no `Set-Cookie`):
  - missing cookie;
  - empty cookie;
  - malformed token;
  - bad signature;
  - expired token;
  - revoked session;
  - expired session;
  - unknown `sid`;
  - `sub` mismatch;
  - `pending` user;
  - `denied` user;
  - deleted user;
  - a Bearer `Authorization` header with no cookie.
- A database exception from the repository propagates and is not turned into a `401`.
- `AdminCheckTest`:
  - an admin passes;
  - a non-admin gets `403` `Admin access required`;
  - with no `AuthenticatedUser` attached it throws `LogicException`, which gives `500`.
- Run both through `RequestHandler::handle()` once with `[Gate, AdminCheck]` to show an anonymous caller gets `401`, not `403`.

## Files to Change
- `source/lib/Auth/Gate.php` — new
- `source/lib/Auth/AdminCheck.php` — new
- `source/tests/unit/Auth/AdminCheckTest.php` — new
- `source/tests/integration/Auth/GateTest.php` — new (database-backed)
