# 01 — Building blocks: optional fields, LockedException, test support

Small, independently tested pieces the later steps rely on.

1. **Optional fields in `Validator` / `FieldRules`.** Add `FieldRules::optional()`: an absent or `null` value produces no error and the field is **omitted** from `Validator::validated()`; a present value runs every rule as today (a present empty string after trim is still `should not be empty` when `notEmpty()` is declared; a present non-string is still `must be a string`). Keep the existing required behavior the default. Widen `validated()`'s docblock accordingly. Unit tests in `ValidatorTest` (absent, null, empty, wrong type, valid; mixed with required fields; message order).
2. **`LockedException`** in `source/lib/Http/` (`423`, optional message and code, like `ConflictException`) with a unit test mirroring `ConflictExceptionTest`.
3. **Password verifier seam.** Introduce a tiny `Pidgey\Auth\PasswordVerifier` interface (`verify(#[SensitiveParameter] string $password, #[SensitiveParameter] ?string $digest): bool`) implemented by `Passwords`, so the service can be tested with a counting double while `Passwords` stays `final`.
4. **Test support.** In `SessionEndpointTestCase`:
   - let `dispatch()` take optional cookies, and add `patch(Router, path, body, cookies = [], headers = [])`;
   - add a helper that starts a session for a user and returns the `access_token` cookie value (`AccessToken` from the container, as the gate verifies it), e.g. `accessCookie(User): array{access_token: string}`;
   - add lockout helpers: `lockoutRow(int $userId): ?array` and `setLockout(int $userId, int $failedAttempts, ?string $lockedUntil)`;
   - add a reset-token fixture helper (insert an unused `password_reset_tokens` row for a user) and a way to read its `used_at`.

## Files to Change
- `source/lib/Http/FieldRules.php` — `optional()`.
- `source/lib/Http/Validator.php` — skip absent optional fields in `validated()`.
- `source/lib/Http/LockedException.php` — new.
- `source/lib/Auth/PasswordVerifier.php` — new interface.
- `source/lib/Auth/Passwords.php` — `implements PasswordVerifier`.
- `source/tests/unit/Http/ValidatorTest.php`, `source/tests/unit/Http/LockedExceptionTest.php` — tests.
- `source/tests/support/SessionEndpointTestCase.php` (and `Fixtures.php` if the token fixture lives there) — helpers.
