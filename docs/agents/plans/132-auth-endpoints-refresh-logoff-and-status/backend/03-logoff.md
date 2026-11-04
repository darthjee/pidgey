# `DELETE /auth/logoff.json`
Add `Pidgey\Http\Handler\LogoffHandler`.

- The constructor takes `Sessions $sessions`. `build(Container)` passes `$container->sessions()`.
- `process()`: validate `refreshToken` (string, not empty, no trim), call `$this->sessions->logout($fields['refreshToken'])`, then build `$response = new Response('', 204)`, apply `AccessTokenCookie::clear()->applyTo($response)`, and return it.

Integration tests (`LogoffHandlerTest.php`), sending `DELETE` with a JSON body through the `delete()` helper:
- missing or empty `refreshToken` → `400 VALIDATION_FAILED`, with no cookie cleared;
- an active token → `204`:
  - the body is empty;
  - the `Set-Cookie` header equals `AccessTokenCookie::clear()`'s line (`Max-Age=0`, `Expires=Thu, 01 Jan 1970 00:00:00 GMT`);
  - every active row of that session is revoked with `logout`;
- the user's **other** session stays active;
- an already-rotated token (refresh once, then log off with the old token) still identifies its session: the current active token of that session is revoked with `logout`;
- an expired token: the session's active rows, if any, are revoked; the answer is `204` either way;
- an already revoked row keeps its original `revoked_reason` and `revoked_at`;
- an unknown token → `204` with the cookie cleared, and no row changes;
- same behaviour for pending, approved and denied users (data provider).

## Files to Change
- `source/lib/Http/Handler/LogoffHandler.php`: new.
- `source/tests/integration/Http/Handler/LogoffHandlerTest.php`: new.
