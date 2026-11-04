# Shared session response and password helpers
Both endpoints answer the same way and share password rules, so put them in one place.

- **`Pidgey\Auth\SessionResponse::created(SessionTokens $tokens, int $accessTtl): JsonResponse`**: status `201`, body `{ "user": UserPayload::fromUser($tokens->user()), "refreshToken": $tokens->refreshToken() }`, and `AccessTokenCookie::set($tokens->accessToken(), $accessTtl)->applyTo($response)`. If `SessionTokens` has no `user()` accessor, add one (it already holds the user). Refresh (#132) and the device poll will reuse this. Unit test: status, exact body keys (`user`, `refreshToken`; never `status`, digest or access token in the body), one `Set-Cookie` with `access_token=…; Max-Age=<ttl>; Path=/; …SameSite=Strict`.
- **`Pidgey\Auth\Passwords`**:
  - `public const DUMMY_DIGEST = '$2y$12$…'`: generate it **once** with `php -r 'echo password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);'` in the dev container (PHP 8.4, cost 12) and paste the literal; the plain string is thrown away. Never computed at runtime.
  - `hash(string $password): string` → `password_hash($password, PASSWORD_DEFAULT)`.
  - `verify(string $password, ?string $digest): bool` → `password_verify($password, $digest ?? self::DUMMY_DIGEST)`, and returns `false` when `$digest` is null even if the dummy verify somehow matched.
  - `needsRehash(string $digest): bool`.
  - All password/digest parameters `#[SensitiveParameter]`.
  - Unit tests: `password_needs_rehash(Passwords::DUMMY_DIGEST, PASSWORD_DEFAULT)` is false (guards against PHP default changes making unknown-user logins faster); `password_get_info(DUMMY_DIGEST)['algo']` is the default; verify with null digest is false; hash/verify round-trip.

## Files to Change
- `source/lib/Auth/SessionResponse.php` — new.
- `source/lib/Auth/Passwords.php` — new.
- `source/lib/Auth/SessionTokens.php` — `user()` accessor, if missing.
- `source/tests/unit/Auth/SessionResponseTest.php`, `source/tests/unit/Auth/PasswordsTest.php`, `source/tests/unit/Auth/SessionTokensTest.php` — tests.
