# Add ApiTokenValue and CreatedApiToken

Create the secret helper and the result object that carries the plain token out of the create flow.

`Pidgey\Auth\ApiTokenValue` (final, static, modelled on `RefreshTokenValue`):

- `public const PREFIX = 'pdg_';`
- `private const BYTES = 32;`
- `generate(): string` → `self::PREFIX . bin2hex(random_bytes(self::BYTES))` (`@throws RandomException`).
- `hash(#[SensitiveParameter] string $plain): string` → `hash('sha256', $plain)`.

`Pidgey\Auth\CreatedApiToken` (final readonly): `public ApiToken $apiToken`, `public string $token` with `#[SensitiveParameter]` on the constructor's `$token` parameter. Add a `__debugInfo()` that omits `token`, so `var_dump`/`print_r` never show it.

Tests (unit):

- `ApiTokenValueTest`: `generate()` matches `/^pdg_[0-9a-f]{64}$/`; two calls differ; `hash()` equals `hash('sha256', ...)` and is 64 hex chars; `PREFIX === 'pdg_'`.
- `CreatedApiTokenTest`: exposes both values; `__debugInfo()` omits the plain token.

## Files to Change
- `source/lib/Auth/ApiTokenValue.php` — new.
- `source/lib/Auth/CreatedApiToken.php` — new.
- `source/tests/unit/Auth/ApiTokenValueTest.php` — new.
- `source/tests/unit/Auth/CreatedApiTokenTest.php` — new.
