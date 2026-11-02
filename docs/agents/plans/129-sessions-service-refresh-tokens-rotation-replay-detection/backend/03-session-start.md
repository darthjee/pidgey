# `Sessions::start()` and container wiring

Create `Pidgey\Auth\Sessions` with this constructor:
- `RefreshTokenRepository $refreshTokens`
- `UserRepository $users`
- `Transaction $transaction`
- `SessionRotation $rotation` (added in step 04; you may add the parameter then)
- `AccessToken $accessToken`
- `WallClock $clock`
- `int $refreshTtl`

Add `Container::sessions()`, a lazy getter that builds the service from the existing getters and `Env::integer('REFRESH_TOKEN_TTL', ConfigValidator::DEFAULT_REFRESH_TOKEN_TTL)`.

`start(User $user): SessionTokens`:
1. Read `$now = $clock->now()` once.
2. Generate the plain token (`RefreshTokenValue::generate()`) and a session id (`SessionUuid::generate()`).
3. Call `$refreshTokens->create(RefreshTokenValue::hash($plain), $user->id, $uuid, $now, $now + refreshTtl seconds, $now)`. `started_at` equals `issued_at`.
4. Issue the JWT with `$accessToken->issue($user->id, $uuid, $user->username, $user->isAdmin)`.
5. Return `new SessionTokens($plain, $jwt, $uuid, $user)`.

It opens **no transaction**. Its docblock says so, and that the caller may wrap it in one. It does not check the user's status: login decides whether to start a session, and the gate blocks pending users.

Tests (integration, `SessionsTest`, which uses a fixed `WallClock` stub and a test `AccessToken` built from the development secret):
- the stored row has `token_hash = sha256(plain)`, and no column holds the plain value;
- the row has `user_id`, the UUID, `issued_at = started_at = now`, `expires_at = now + ttl`, and is not revoked;
- the returned JWT verifies, and its claims carry `sid = sessionUuid` and `sub = user id`;
- two starts for the same user create two distinct sessions;
- `start()` works inside an outer `Transaction::run()`, which shows it does not nest.
- A `ContainerTest` case checks that `sessions()` is lazy and memoized and opens no PDO connection until it is used, matching the existing laziness tests.

## Files to Change
- `source/lib/Auth/Sessions.php`: new, with `start()`.
- `source/lib/Container.php`: add `sessions()`, plus `sessionRotation()` once step 04 lands.
- `source/tests/integration/Auth/SessionsTest.php`: new.
- `source/tests/unit/ContainerTest.php` and/or `source/tests/integration/ContainerTest.php`: add the laziness case.
