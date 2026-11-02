# Logout, bulk revoke and status support

Add the remaining public methods to `Sessions`:

- **`logout(#[SensitiveParameter] string $plain): void`:**
  - look up the token by its hash **whatever its state** (expired, rotated or revoked tokens still identify their session);
  - when it is found, call `revokeActiveForSession($token->userId, $token->sessionUuid, Logout, $now)`;
  - an unknown token is a silent no-op.
- **`revokeAllForUser(int $userId, RevokedReason $reason): void`** calls `revokeActiveForUser($userId, $reason, $now)`. It is for password reset, admin deny and admin edit later.
- **`findActive(#[SensitiveParameter] string $plain): ?ActiveSession`:**
  - look up the token by its hash;
  - return `null` unless it is found, has `revokedAt === null` and has `expiresAt > now`, and its user row exists;
  - it never rotates and never runs replay detection.

  `Pidgey\Auth\ActiveSession` is a final readonly value holding `RefreshToken $token` and `User $user`.
- **`revoke(RefreshToken $token, RevokedReason $reason): void`** calls `revokeIfActive($token->id, $reason, $now)`. Status (#132) uses it for a denied user.

Tests (integration, `SessionsTest`):
- **logout:**
  - revokes every active row of that session with `logout`, and leaves the user's other sessions active;
  - presenting an already-rotated or expired token of a session still revokes that session's active row;
  - an unknown token changes nothing.
- **`revokeAllForUser`:** revokes every active row of the user with the given reason, leaves already-revoked rows' reasons and times unchanged, and does not touch other users.
- **`findActive`:**
  - returns the token and user for an active token;
  - returns null for an unknown, revoked or expired token;
  - after a call, no row has changed.
- **`revoke`:** revokes the given active row with the reason, and is a no-op on an already-revoked row.
- **Logging:** a test that captures the logger, or asserts that no logger is called, shows that no operation writes the plain token. If `Sessions` takes no logger, record that in the docblock instead.

## Files to Change
- `source/lib/Auth/Sessions.php`: add `logout`, `revokeAllForUser`, `findActive` and `revoke`.
- `source/lib/Auth/ActiveSession.php`: new.
- `source/tests/integration/Auth/SessionsTest.php`: add the cases above.
