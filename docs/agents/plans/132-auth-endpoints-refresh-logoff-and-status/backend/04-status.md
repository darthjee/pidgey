# `POST /auth/status.json`
Add `Pidgey\Http\Handler\StatusHandler`.

- The constructor takes `Sessions $sessions` only (no `AccessToken`, since status never touches the cookie). `build(Container)` passes `$container->sessions()`.
- `process()`: validate `refreshToken` (string, not empty, no trim), then `$session = $this->sessions->findActive($plain)`, and map the result:
  - null → `self::ANONYMOUS` = `['loggedIn' => false, 'isAdmin' => false]`;
  - `UserStatus::Denied` → `$this->sessions->revoke($session->token, RevokedReason::AdminDenied)`, then `self::ANONYMOUS`;
  - `UserStatus::Pending` → `['loggedIn' => false, 'isAdmin' => false, 'status' => 'pending']`;
  - `UserStatus::Approved` → `['loggedIn' => true, 'isAdmin' => $session->user->isAdmin]`.
- Return `new JsonResponse($body, 201)` and never add a `Set-Cookie`. Use a `match` on the status so a new enum case fails statically.

Integration tests (`StatusHandlerTest.php`):
- missing or empty `refreshToken` → `400 VALIDATION_FAILED`;
- approved non-admin → `{loggedIn: true, isAdmin: false}`; approved admin → `{loggedIn: true, isAdmin: true}`. `isAdmin` comes from the DB row: flip `is_admin` after the session started and assert the new value;
- pending → `{loggedIn: false, isAdmin: false, status: "pending"}`, and the token row is unchanged (still active);
- denied → the response body string equals the unknown-token body string **byte for byte**, and the presented token is revoked with `admin_denied`;
- unknown token, a token revoked with `rotated` (no replay detection: the user's current token stays active), a token revoked for another reason, an expired token, and a missing user (if reachable, see step 02) → the anonymous body, with no row changes;
- no response ever carries a `Set-Cookie` header;
- calling status twice with the same active token returns the same answer and does not rotate, so the token still refreshes afterwards.

## Files to Change
- `source/lib/Http/Handler/StatusHandler.php`: new.
- `source/tests/integration/Http/Handler/StatusHandlerTest.php`: new.
