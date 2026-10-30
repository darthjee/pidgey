# Repositories and container

Add one `final` repository per table in `Pidgey\Repository\`, shaped like `UserRepository`:

- a `COLUMNS` constant;
- named placeholders only;
- a private `fetchOne()`;
- `create()` inserts, then re-reads by `lastInsertId()` through `findById()`, throwing
  `LogicException` (naming the id, never a digest) when the row cannot be read back;
- `DateTimeImmutable` arguments bound through `Sql::dateTime()`;
- no `NOW()`, no transactions.

Docblocks follow the existing style. Each unscoped lookup names the spec that needs it:

- token digests: api/authentication/sessions.md, api/endpoints/auth/recovery.md;
- uuid and poll token: api/endpoints/auth/device-authorization.md;
- lockouts: api/endpoints/auth/account.md.

## Repositories

- **`RefreshTokenRepository`**
  - `create(tokenHash, userId, sessionUuid, issuedAt, expiresAt, startedAt)`: inserts
    `issued_at` explicitly; `revoked_at`/`revoked_reason` stay null.
  - `findById()`, `findByTokenHash(string $tokenHash)`.
- **`PasswordResetTokenRepository`**
  - `create(tokenHash, userId, createdAt, expiresAt)`: inserts `created_at` explicitly.
  - `findById()`, `findByTokenHash()`.
- **`AuthorizationRequestRepository`**
  - `create(uuid, username, ?userId, pollTokenHash, requestIp, requestUserAgent, createdAt,
    expiresAt)`: inserts `created_at` explicitly. No `status` or attempts columns in the
    `INSERT`, so the DB defaults (`open`, `0`) apply.
  - `findById()`, `findByUuid(string $uuid)`, `findByPollTokenHash(string $pollTokenHash)`.
- **`AccountEditLockoutRepository`**
  - `create(int $userId)`: inserts `user_id` only.
  - `findById()`, `findByUserId(int $userId)`.

## Container

Add memoized getters to `Container`, with docblocks like the existing ones:

- `refreshTokenRepository()`;
- `passwordResetTokenRepository()`;
- `authorizationRequestRepository()`;
- `accountEditLockoutRepository()`.

Extend `tests/unit/ContainerTest.php`: each getter returns its type, is memoized, and is
built on the single PDO. Add `#[UsesClass]` entries for the four repositories.

## Files to Change

- `source/lib/Repository/RefreshTokenRepository.php` — new.
- `source/lib/Repository/PasswordResetTokenRepository.php` — new.
- `source/lib/Repository/AuthorizationRequestRepository.php` — new.
- `source/lib/Repository/AccountEditLockoutRepository.php` — new.
- `source/lib/Container.php` — four getters and their properties.
- `source/tests/unit/ContainerTest.php` — getter tests.
