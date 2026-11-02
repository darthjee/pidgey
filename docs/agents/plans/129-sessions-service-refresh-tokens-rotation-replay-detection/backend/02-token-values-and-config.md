# Token values, the TTL default and `SessionTokens`

This step adds the building blocks the service needs.

- **TTL default:** make `ConfigValidator::DEFAULT_REFRESH_TOKEN_TTL` a public `int` constant (`604800`), the same shape as `DEFAULT_ACCESS_TOKEN_TTL`, and update its internal use, which currently casts a string.
- **`Pidgey\Auth\RefreshTokenValue`** (a final class with static helpers):
  - `generate(): string` returns `bin2hex(random_bytes(48))`, 96 lowercase hex characters;
  - `hash(#[SensitiveParameter] string $plain): string` returns `hash('sha256', $plain)`, 64 hex characters.
- **`Pidgey\Auth\SessionUuid::generate(): string`** builds a UUID v4 from `random_bytes(16)`, with version nibble `4` and variant `10xx`, and returns it as a lowercase string in the 8-4-4-4-12 form. It must pass the gate's UUID check.
- **`Pidgey\Auth\SessionTokens`** is a final readonly value with these fields:
  - `string $refreshToken`, the plain value;
  - `string $accessToken`, the JWT;
  - `string $sessionUuid`;
  - `User $user`.

  Mark the token constructor parameters `#[SensitiveParameter]`, and add a `__debugInfo()` that redacts `refreshToken` and `accessToken`.

Tests (unit):
- **Refresh token values:**
  - generated values have 96 characters, contain only hex, and are distinct across calls;
  - the hash is the known SHA-256 of a fixed input.
- **Session UUIDs:** the format matches `/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/`, and generated values are distinct.
- **`SessionTokens`:** the `__debugInfo()` output and `print_r` show neither token.
- **Config:** the existing `ConfigValidator` tests still pass with the int constant.

## Files to Change
- `source/lib/Infra/ConfigValidator.php`: make `DEFAULT_REFRESH_TOKEN_TTL` public and an `int`.
- `source/lib/Auth/RefreshTokenValue.php`: new.
- `source/lib/Auth/SessionUuid.php`: new.
- `source/lib/Auth/SessionTokens.php`: new.
- `source/tests/unit/Auth/RefreshTokenValueTest.php`, `SessionUuidTest.php`, `SessionTokensTest.php`: new.
