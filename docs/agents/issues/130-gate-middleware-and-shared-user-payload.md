# Issue: Gate middleware and shared `user` payload

## Description
Part of epic #120 (auth API). This issue implements the per-request gate of the cookie-authenticated surface (`specs/api/authentication/gate.md`): access-token verification, a one-query user and session lookup, the uniform `401`, and attaching the caller to the processing request. It also adds:
- the admin check;
- the dispatcher plumbing the gate needs;
- the shared `user` payload builder used by register, login, refresh and the device poll.

Depends on #128 (merged): `AccessToken::verify()`, `AccessTokenClaims` and `Request::cookie()`.

## Problem
No route can require a logged-in user yet. The specs define the gate, but the code has nothing to plug it into:
- `FrontController` builds handlers and middlewares with `new $class()`. There is no `build(Container)`, no `HandlerFactory` and no per-request `Container`, so a middleware cannot get the database or the access-token verifier.
- `ProcessingRequest` has no `attach()` / `attached()` / `require()`.
- `UnauthorizedException`, `Pidgey\Auth\Caller` and the authenticated-user model do not exist.

## Expected Behavior
- A route that lists `Gate` answers the uniform `401` when any of these holds:
  - the `access_token` cookie is missing;
  - the token fails strict verification;
  - there is no matching approved user with a live session.
- That `401` is `{ "error": { "code": "UNAUTHORIZED", "message": "Invalid or expired access token" }, "statusCode": 401, "timestamp": "..." }`. Its status, headers and body are byte-identical across failures apart from `timestamp`, and it never sends `Set-Cookie`.
- A database error during the lookup answers `500 INTERNAL_ERROR`, never `401`.
- On success, the handler reads the caller with `$request->require(AuthenticatedUser::class)` or `require(Caller::class)`. `username`, `isAdmin` and `organizationId` come from the database row, never from the JWT.
- A Bearer `Authorization` header is ignored by the gate.
- On a route that lists `AdminCheck` after `Gate`, an authenticated non-admin gets `403 FORBIDDEN` `Admin access required`. An anonymous caller gets the gate's `401` first. `AdminCheck` without `Gate` answers `500` (via `LogicException` from `require()`).

## Solution
### 1. Dispatcher plumbing (per `specs/request-handler/handlers.md`, `middlewares.md`, `request-response/processing-request.md`)
- `RequestHandler` and the `Middleware` interface declare `public static function build(Container $container): static`. `RequestHandler` and `BaseMiddleware` default it to `new static()`. `OriginCheck` and any existing class with constructor arguments override it.
- `Pidgey\Http\HandlerFactory` wraps a `Container`:
  - `handler(string $class): RequestHandler` calls `$class::build($container)`;
  - `middlewares(array $classes): array` builds each class and throws `LogicException` for a class that is not a middleware.
- `FrontController` creates **one `Container` per request**, builds the handler and the global-then-route middlewares through the factory, and stays lazy: `/health.json` and the static routes open no database connection.
- `Container` gains lazy, memoized getters for what the gate needs: `wallClock(): WallClock` and `accessToken(): AccessToken`.
- `ProcessingRequest` gains `attach(object)`, `attached(string $class): ?object` and `require(string $class): object`, following the spec:
  - attaching a second object of the same class throws `LogicException`;
  - more than one match also throws `LogicException`;
  - `require()` on a missing object throws `LogicException`, which answers `500`.
- New `Pidgey\Http\UnauthorizedException` (`401`).

### 2. Caller and authenticated user
- `Pidgey\Auth\Caller` has one method, `organizationId(): int`.
- `Pidgey\Auth\AuthenticatedUser` (`final readonly`) has `id`, `sessionId`, `username`, `isAdmin` and `organizationId`, and implements `Caller`.

### 3. Gate query
- `UserRepository::findApprovedBySession(int $userId, string $sessionUuid, DateTimeImmutable $now): ?User` runs **one** query: `users` joined to `refresh_tokens` on `user_id`.
- Conditions:
  - `session_uuid = :sid`;
  - `revoked_at IS NULL`;
  - `expires_at > :now`, where `now` comes from the `WallClock` and is bound from PHP;
  - `users.status = 'approved'`;
  - `users.id = :sub`.

### 4. `Pidgey\Auth\Gate` middleware
- `build()` takes the `AccessToken` verifier, `UserRepository` and `WallClock` from the container.
- `processRequest()`:
  1. reads the `access_token` cookie;
  2. calls `AccessToken::verify()`;
  3. calls `findApprovedBySession(sub, sid, now)`;
  4. attaches an `AuthenticatedUser` built from the database row plus `sid`.
- Any failure in steps 1–3 throws the same `UnauthorizedException`, with the same message. Database exceptions are not caught, so they become `500`.
- The `Authorization` header is never read.

### 5. `Pidgey\Auth\AdminCheck` middleware
- Reads `require(AuthenticatedUser::class)` and throws `ForbiddenException('Admin access required')` when `isAdmin` is false.

### 6. Shared `user` payload
- `Pidgey\Auth\UserPayload::fromUser(User $user): array` returns `{ id, username, email, isAdmin }`.
- It never includes `status`, the password digest or any token.
- Each endpoint composes the rest of the body (`refreshToken` from the #129 sessions service, `status` for the device poll) and the `Set-Cookie`. The status endpoint does not use it.

### 7. Integration tests
- **Deny-by-default routes test** over `config/routes.php`:
  - every route outside the public allowlist (`GET /`, `GET /assets/*`, `GET /health.json`, the GET fallback) lists `Gate` or `ApiTokenAuth`;
  - `AdminCheck` only appears after `Gate`.
- **Factory test:** builds every routed handler and middleware with the real test container.

## Benefits
- Unblocks every authenticated endpoint (#131–#133 and the admin and organization routes).
- Session revocation, user deny and admin demotion take effect on the next request, because the gate reads the database each time.
- The deny-by-default test makes forgetting the gate on a new route a CI failure.

## Acceptance

- Gate unit tests, each failure asserting the identical uniform `401`:
  - a valid caller is attached;
  - missing cookie;
  - malformed, bad-signature and expired token;
  - a token signed with a previous key still passes;
  - revoked session, expired session, unknown `sid`;
  - `sub` not matching the session's user;
  - user `pending` or `denied`, and a deleted user;
  - a Bearer header alone gives `401`.
- A database error in the lookup is a `500`.
- `AdminCheck` tests: admin passes, non-admin gets `403`, and running without `Gate` gives `500`.
- `ProcessingRequest` attach tests: duplicate attach, ambiguous match, and `require()` on a missing object.
- `HandlerFactory` tests: a non-middleware class is rejected.
- A test shows `/health.json` opens no database connection.
- Repository test for `findApprovedBySession()` against the test database.
- Deny-by-default routes test and integration factory test.
- `security` agent review before the PR is marked ready.
