# Enums and entities

Add the enums and entities to `Pidgey\Model\`, following `User`/`UserStatus`.

## Enums

Both are string-backed, and their case values are the database values.

- `RevokedReason`: `Rotated = 'rotated'`, `Logout = 'logout'`,
  `PasswordReset = 'password_reset'`, `ReplayDetected = 'replay_detected'`,
  `AdminDenied = 'admin_denied'`, `AdminEdit = 'admin_edit'`.
- `AuthorizationRequestStatus`: `Open`, `Approved`, `Denied`, `Logged`, `Expired`, in
  lowercase.

## Entities

Each entity is a `final readonly class` with one public promoted property per column, in
camelCase, and a `fromRow()` built on `Row`.

- **`RefreshToken`**
  - `id`, `tokenHash`, `userId`, `issuedAt`, `expiresAt`;
  - `?revokedAt`, `?RevokedReason revokedReason` (via `Row::nullableEnum`);
  - `sessionUuid`, `startedAt`.
- **`PasswordResetToken`**: `id`, `tokenHash`, `userId`, `createdAt`, `expiresAt`,
  `?usedAt`.
- **`AuthorizationRequest`**
  - `id`, `uuid`, `username`, `?userId`, `AuthorizationRequestStatus status`;
  - `pollTokenHash`, `requestIp`, `requestUserAgent`, `?approvedByUserId`;
  - `createdAt`, `expiresAt`, `?resolvedAt`, `?loggedAt`;
  - `authorizeFailedAttempts`, `?authorizeLockedUntil`.
- **`AccountEditLockout`**: `id`, `userId`, `failedAttempts`, `?lockedUntil`, `createdAt`,
  `updatedAt`.

No helper methods: they are deferred to the endpoint issues.

## Tests

Unit tests in `source/tests/unit/Model/`, one per entity. Each covers:

- every column converted, with dates in UTC;
- nullable columns as `null`, and as values when set;
- a missing column → `LogicException`;
- for the two enum columns, an unknown value → `UnexpectedValueException`;
- every `RevokedReason` and `AuthorizationRequestStatus` case parsing (data provider).

## Files to Change

- `source/lib/Model/RevokedReason.php` — new.
- `source/lib/Model/AuthorizationRequestStatus.php` — new.
- `source/lib/Model/RefreshToken.php` — new.
- `source/lib/Model/PasswordResetToken.php` — new.
- `source/lib/Model/AuthorizationRequest.php` — new.
- `source/lib/Model/AccountEditLockout.php` — new.
- `source/tests/unit/Model/RefreshTokenTest.php` — new.
- `source/tests/unit/Model/PasswordResetTokenTest.php` — new.
- `source/tests/unit/Model/AuthorizationRequestTest.php` — new.
- `source/tests/unit/Model/AccountEditLockoutTest.php` — new.
