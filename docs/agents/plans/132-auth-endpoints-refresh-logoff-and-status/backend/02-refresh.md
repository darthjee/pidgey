# `POST /auth/refresh.json`
Add `Pidgey\Http\Handler\RefreshHandler` (final, extends `RequestHandler`), modelled on `LoginHandler`.

- The constructor takes `Sessions $sessions, AccessToken $accessToken`. `build(Container)` passes `$container->sessions()` and `$container->accessToken()`.
- `process()`:
  1. `Validator` on `$request->request()->json()`: `string('refreshToken')->notEmpty()` (no trim), then `validated()`;
  2. `$tokens = $this->sessions->refresh($fields['refreshToken'])`;
  3. null → throw `UnauthorizedException(self::INVALID_TOKEN_MESSAGE)` (`'Invalid or expired refresh token'`, code `UNAUTHORIZED`);
  4. otherwise return `SessionResponse::created($tokens, $this->accessToken->ttl())`.
- Docblock: public, ungated, the body is never logged, the single message hides which check failed.

Integration tests (`source/tests/integration/Http/Handler/RefreshHandlerTest.php`, extending `SessionEndpointTestCase`). Every `401` case asserts the same status, code and message, and no `Set-Cookie` header:
- missing body, missing field, empty string, non-string → `400 VALIDATION_FAILED`; a value with surrounding spaces is not trimmed (it is not found → `401`);
- pending and approved users → `201`:
  - the body has `user` (equal to `UserPayload::fromUser`) and a **new** `refreshToken` that differs from the presented one;
  - the `access_token` cookie is set, and its JWT `sid` equals the original session uuid;
  - the old row is revoked with `rotated`;
- unknown token → `401`;
- expired token → `401`;
- replay: refresh once, then present the old (rotated) token again → `401`, and every active token of the user, including another session, is revoked with `replay_detected`;
- token revoked with `logout` → `401`, with no replay side effect (the user's other session stays active);
- user row missing → `401`. Delete the user, or point the token at a missing id, whichever the FK constraints allow; if a FK cascade makes this unreachable through the DB, cover it at the `Sessions` unit level only and note it in the test;
- denied user → `401`, and the presented token is revoked with `admin_denied`. No replay detection: the user's other active session is untouched.
- The lost-rotation race is already covered by the #129 `Sessions`/`SessionRotation` tests. Here, only assert that a null from `refresh()` maps to the same `401` (the cases above already prove that).

## Files to Change
- `source/lib/Http/Handler/RefreshHandler.php`: new.
- `source/tests/integration/Http/Handler/RefreshHandlerTest.php`: new.
