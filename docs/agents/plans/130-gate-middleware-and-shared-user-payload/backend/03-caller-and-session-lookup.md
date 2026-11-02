# Caller, AuthenticatedUser and the session lookup query

- **`Pidgey\Auth\Caller`:** an interface with `organizationId(): int` (`middlewares.md#caller-interface`).
- **`Pidgey\Auth\AuthenticatedUser`:** `final readonly`, implements `Caller`.
  - Fields: `int $id`, `string $sessionId`, `string $username`, `bool $isAdmin`, `int $organizationId`.
  - `organizationId()` returns the field.
  - It is built from the database `User` plus the `sid` claim, never from the JWT's `username` or `isAdmin`.
- **`UserRepository::findApprovedBySession(int $userId, string $sessionUuid, DateTimeImmutable $now): ?User`:** **one** prepared query.

  ```sql
  SELECT u.* FROM users u
  JOIN refresh_tokens rt ON rt.user_id = u.id
  WHERE rt.session_uuid = :sid
    AND rt.revoked_at IS NULL
    AND rt.expires_at > :now
    AND u.status = 'approved'
    AND u.id = :sub
  LIMIT 1
  ```

  - Bind `now` from PHP, formatted the way the repositories already store datetimes (see `sessions.md#clock` and the existing repositories). Never use `NOW()`.
  - Reuse the existing `fetchOne()` and `User::fromRow()`.
  - Several rows can match for one session, because rotation keeps the older rows. `LIMIT 1` with any matching live row is fine. Check the `sessions.md` rule on rotated rows: if rotated rows are marked with `revoked_at`, nothing extra is needed.
  - Confirm the table and column names against the migrations under `source/` before writing the SQL.

Tests (integration, `RepositoryTestCase` with `Fixtures`). The query returns the user for a live, approved session, and `null` for each of these:
- revoked token;
- expired token (`expires_at <= now`);
- unknown `sid`;
- `sid` belonging to another user (`sub` mismatch);
- user `pending`;
- user `denied`;
- deleted user (no row).

## Files to Change
- `source/lib/Auth/Caller.php` — new
- `source/lib/Auth/AuthenticatedUser.php` — new
- `source/lib/Repository/UserRepository.php` — `findApprovedBySession()`
- `source/tests/unit/Auth/AuthenticatedUserTest.php` — new
- `source/tests/integration/Repository/UserRepositoryTest.php` — extend
- `source/tests/support/Fixtures.php` — refresh-token fixture helpers, if missing
