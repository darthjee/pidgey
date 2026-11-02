# Conditional revoke methods on the repository

Add three revoke methods to `RefreshTokenRepository`. Each one writes `revoked_at` and `revoked_reason` together, binds `now` as a parameter, touches only **active** rows (`revoked_at IS NULL`), and returns `rowCount()`.

- `revokeIfActive(int $id, RevokedReason $reason, DateTimeImmutable $now): int` runs:

  ```sql
  UPDATE refresh_tokens SET revoked_at = :now, revoked_reason = :reason
  WHERE id = :id AND revoked_at IS NULL AND expires_at > :now
  ```

  This is the conditional revoke of rotation, also used for the single-token `admin_denied` revoke.
- `revokeActiveForUser(int $userId, RevokedReason $reason, DateTimeImmutable $now): int` revokes `WHERE user_id = :user_id AND revoked_at IS NULL`. It is used for replay detection, password reset, admin deny and admin edit.
- `revokeActiveForSession(int $userId, string $sessionUuid, RevokedReason $reason, DateTimeImmutable $now): int` revokes `WHERE user_id = :user_id AND session_uuid = :session_uuid AND revoked_at IS NULL`. It is used for logout.

The bulk revokes do not filter on `expires_at`. The spec says "every active row", and revoking an expired row is harmless.

Tests (integration, `RefreshTokenRepositoryTest`):
- `revokeIfActive`:
  - revokes an active row and returns 1;
  - returns 0 for an already-revoked row and keeps its original reason and time;
  - returns 0 for an expired row (`expires_at <= now`);
- `revokeActiveForUser`:
  - revokes all of the user's active rows;
  - leaves rows that were already revoked unchanged;
  - does not touch other users;
- `revokeActiveForSession`:
  - revokes only that session's active rows;
  - does not touch the user's other sessions;
  - ignores a matching `session_uuid` that belongs to another user.

## Files to Change
- `source/lib/Repository/RefreshTokenRepository.php`: add the three methods with docblocks.
- `source/tests/integration/Repository/RefreshTokenRepositoryTest.php`: add the tests above.
