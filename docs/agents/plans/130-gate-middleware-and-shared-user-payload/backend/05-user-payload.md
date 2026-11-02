# Shared user payload
Add **`Pidgey\Auth\UserPayload`**, a final class with a static `fromUser(User $user): array`.

- It returns `['id' => int, 'username' => string, 'email' => string, 'isAdmin' => bool]` (`gate.md#shared-user-payload`).
- It never includes `status`, the password digest, `organization_id` or any token.
- It is only the `user` object. The endpoints in #131 and #132 compose `{ "user": ..., "refreshToken": ... }` and the `Set-Cookie`. The status endpoint does not use it.

Tests:
- exact keys and types (`assertSame` on the whole array);
- no `status` or password key.

## Files to Change
- `source/lib/Auth/UserPayload.php` — new
- `source/tests/unit/Auth/UserPayloadTest.php` — new
