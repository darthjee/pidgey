# Rotation and `Sessions::refresh()`

## `Pidgey\Auth\SessionRotation`

Its constructor takes `RefreshTokenRepository`, `Transaction`, `AccessToken`, `WallClock` and `int $refreshTtl`. It is also built by `Container::sessionRotation()`.

`rotate(RefreshToken $presented, User $user): ?SessionTokens` runs inside `Transaction::run()`:
1. Read `$now` once.
2. Create a new row with a new plain token, the **same** `session_uuid` and `started_at` as `$presented`, `issued_at = now` and `expires_at = now + ttl`.
3. Call `revokeIfActive($presented->id, RevokedReason::Rotated, $now)`.
4. If that affects **0 rows**, throw a private sentinel exception inside the closure so `Transaction` rolls back, catch it outside, and return `null`. There is no other side effect and no replay revocation.
5. Otherwise, after the commit, issue the JWT with the same `sid` and return `SessionTokens`.

## `Sessions::refresh(#[SensitiveParameter] string $plain): ?SessionTokens`

It follows the order of the spec's table:
1. Look up the token by `RefreshTokenValue::hash($plain)`.
   - Not found → `null`.
   - `expiresAt <= now` → `null`.
2. `revokedReason === Rotated`: this is a **replay**. Run `revokeActiveForUser($token->userId, ReplayDetected, $now)` as its own statement, outside any transaction, so it commits. Then return `null`.
3. Revoked for any other reason → `null`, with no side effect.
4. `users->findById($token->userId)` returns null → `null`.
5. The user is `denied`: run `revokeIfActive($token->id, AdminDenied, $now)`, then return `null`. There is no replay detection.
6. The user is `pending` or `approved`: return `$rotation->rotate($token, $user)`. That is `null` on a lost race.

Every failure is the same `null`. Do not log the branch taken.

Tests (integration):
- **`SessionRotationTest`:**
  - success:
    - the new row copies `session_uuid` and `started_at`, with a fresh `issued_at` and `expires_at`;
    - the old row is revoked with `rotated` at `now`;
    - the returned plain token hashes to the new row;
    - the JWT `sid` is unchanged;
  - **lost race:** read the token entity, then revoke the row directly in the database (any reason), then call `rotate()` with the stale entity. It returns `null`, the row count is unchanged (the new row was rolled back), the old row keeps its reason, and no other row of the user was revoked;
  - **expired in between:** the entity was read while active, but the clock passed `expires_at` before `rotate()`. It returns `null`, also rolled back.
- **`SessionsTest`, refresh:**
  - an approved user and a pending user each rotate successfully;
  - an unknown token, an expired token, and tokens revoked as `logout`, `password_reset`, `admin_denied`, `admin_edit` and `replay_detected` each return `null`, with no row changed;
  - **replay:** re-presenting a `rotated` token returns `null`. Every active token of that user, across all sessions, is now `replay_detected`, while already-revoked rows and other users' rows are unchanged;
  - **denied user:** the presented token is revoked with `admin_denied`, the user's other sessions are untouched, and it returns `null`;
  - a missing user row returns `null`. Create the token, then delete the user with FK checks off, or use a fixture that allows this; if the FK makes it impossible, note that and cover the branch with a unit test that uses a stub repository through an interface-free path. Otherwise, document why it can only happen through a race;
  - a chain: the first refresh succeeds, refreshing again with the new token succeeds, and refreshing with the first token is a replay.

## Files to Change
- `source/lib/Auth/SessionRotation.php`: new.
- `source/lib/Auth/Sessions.php`: add `refresh()`, and take `SessionRotation` in the constructor.
- `source/lib/Container.php`: add `sessionRotation()` and wire it into `sessions()`.
- `source/tests/integration/Auth/SessionRotationTest.php`: new.
- `source/tests/integration/Auth/SessionsTest.php`: add the refresh cases.
