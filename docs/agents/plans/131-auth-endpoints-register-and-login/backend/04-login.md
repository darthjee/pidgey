# Login service and login handler
**`Pidgey\Auth\Login`** (built in `Container::login()` from the user repository and `sessions()`):

- `login(string $username, #[SensitiveParameter] string $password): SessionTokens`
  1. `$user = UserRepository::findByUsername($username)` (collation does case/accent; no email fallback).
  2. `$ok = Passwords::verify($password, $user?->passwordDigest)`, always exactly one verify; unknown user → verified against `DUMMY_DIGEST`.
  3. `!$ok` (or no user) → `UnauthorizedException('Invalid username or password')` (code `UNAUTHORIZED`), whatever the status.
  4. `status === Denied` → `ForbiddenException('This account has been denied access', 'USER_DENIED')`; no session.
  5. `Passwords::needsRehash($digest)` → `updatePasswordDigest($user->id, Passwords::hash($password))`.
  6. `Sessions::start($user)` (no transaction needed: one insert, plus the optional rehash before it).
- Check the `User` model's property names (`passwordDigest`, `status`) and adapt.

**`Pidgey\Http\Handler\LoginHandler`** (`build()` takes `login()` and `accessToken()`):
- `Validator`: `username`: `trim()->notEmpty()`; `password`: `notEmpty()` only (never trimmed, no max length).
- `Login::login()`, then `SessionResponse::created(...)`.

Tests (`tests/integration/Auth/LoginTest.php`, plus handler tests):
- approved user → `201`, new active refresh token; `pending` user → `201` too.
- `BOB` logs in as `bob`; logging in with the email as username → `401`.
- unknown username → `401` with the same code and message as a wrong password (assert equal envelopes).
- wrong password for an approved, a pending and a **denied** user → identical `401`.
- right password for a denied user → `403 USER_DENIED`, no refresh token row, no `Set-Cookie`.
- rehash: a user whose digest uses a lower cost (`password_hash($p, PASSWORD_BCRYPT, ['cost' => 4])`) gets a new `PASSWORD_DEFAULT` digest after login; a current digest is not rewritten.
- validation: missing/empty/whitespace-only username, missing/empty password → `400 VALIDATION_FAILED`; password `"  x  "` is not trimmed.
- two logins → two distinct sessions (different `session_uuid`s).

## Files to Change
- `source/lib/Auth/Login.php` — new.
- `source/lib/Http/Handler/LoginHandler.php` — new.
- `source/lib/Container.php` — `login()`.
- `source/tests/integration/Auth/LoginTest.php`, `source/tests/integration/Http/Handler/LoginHandlerTest.php` (or unit) — tests.
