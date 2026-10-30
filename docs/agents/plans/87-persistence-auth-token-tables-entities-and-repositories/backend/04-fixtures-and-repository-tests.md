# Fixtures and repository tests

## Fixtures

Add methods to `Pidgey\Tests\Support\Fixtures`. All use plain SQL through `insert()`, named
overrides and valid defaults, and return the new id.

- **Shared helpers:**
  - private `digest()` (`bin2hex(random_bytes(32))`, 64 hex);
  - private `uuid()` (random v4 string);
  - private `at(string $relative)`, formatting a UTC `Y-m-d H:i:s` via `Sql::dateTime()`
    or an equivalent `gmdate()`.
- **Methods:**
  - `refreshToken(?int $userId = null, ?string $tokenHash = null, ?string $sessionUuid = null, ...)`:
    defaults are a fresh user, a random digest and uuid, `expires_at` in the future, and
    `started_at = issued_at`.
  - `passwordResetToken(?int $userId = null, ?string $tokenHash = null, ...)`.
  - `authorizationRequest(int|false|null $userId = false, ?string $uuid = null, ?string $pollTokenHash = null, string $status = 'open', ...)`:
    `false` (the default) creates a user, and `null` stores a null `user_id` (unknown
    username). An equivalent explicit flag is fine if clearer.
  - `accountEditLockout(?int $userId = null, int $failedAttempts = 0, ?string $lockedUntil = null)`.
- **Rules:**
  - Each method creates the user when no `userId` is given.
  - Defaults are random digests, never something derived from a plain token.

## Repository tests

Add `source/tests/integration/Repository/<Name>RepositoryTest.php` for each repository,
extending `RepositoryTestCase`, with `#[CoversClass]`/`#[UsesClass]` like
`UserRepositoryTest`.

**Every repository:**

- `create()` returns every field and equals `findById()` on the new id;
- `findById()` / the lookups return `null` when not found;
- a non-existent `user_id` → `PDOException` (FK). Use a user id from a fixture plus a large
  offset, never a literal.

**Refresh tokens:**

- `issued_at`/`expires_at`/`started_at` round-trip in UTC, including a non-UTC input
  (e.g. `America/Sao_Paulo`) stored as its UTC instant;
- revoke fields are null after `create()`;
- `findByTokenHash()` finds the row;
- a duplicate `token_hash` → `PDOException`;
- a fixture row with each `revoked_reason` reads back as the enum (data provider).

**Password reset tokens:**

- explicit `created_at` stored as given (not the DB clock);
- `used_at` null;
- `findByTokenHash()` finds the row;
- a duplicate `token_hash` → `PDOException`.

**Authorization requests:**

- `status = Open`, `authorizeFailedAttempts = 0` and the null event columns after
  `create()`;
- a null `userId` is accepted;
- `findByUuid()` and `findByPollTokenHash()` find the row;
- a duplicate `uuid` and a duplicate `poll_token_hash` → `PDOException`;
- an unknown `approved_by_user_id` in a fixture → `PDOException`.

**Account edit lockouts:**

- defaults after `create()`: `failedAttempts = 0`, `lockedUntil` null, `createdAt`
  recent;
- `findByUserId()` finds the row, and returns `null` for a user without one;
- a second `create()` for the same user → `PDOException`.

**Isolation:** no DDL and no literal ids.

## Files to Change

- `source/tests/support/Fixtures.php` — four fixture methods and their helpers.
- `source/tests/integration/Repository/RefreshTokenRepositoryTest.php` — new.
- `source/tests/integration/Repository/PasswordResetTokenRepositoryTest.php` — new.
- `source/tests/integration/Repository/AuthorizationRequestRepositoryTest.php` — new.
- `source/tests/integration/Repository/AccountEditLockoutRepositoryTest.php` — new.
