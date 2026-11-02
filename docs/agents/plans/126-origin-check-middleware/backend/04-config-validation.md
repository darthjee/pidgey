# ALLOWED_ORIGINS config validation

Add `checkAllowedOrigins()` to `Pidgey\Infra\ConfigValidator`, called from `validate()` after the existing checks.

- **Unset or empty value:** no failure.
- **Each trimmed, non-empty entry:**
  - not a bare origin → `ALLOWED_ORIGINS: invalid origin`. Use the same `^(https?)://host(:port)?$` shape as `OriginCheck`'s `Origin` parser, so a trailing `/`, a path, user info, no scheme or an `ftp://` scheme all fail;
  - otherwise, scheme `http` and `PHP_ENV` not exactly `development` or `test` → `ALLOWED_ORIGINS: not https`. Unset `PHP_ENV` counts as production.
- **Reporting:** each failure kind once, sorted like `checkPreviousSecretKeys()`. Never include the value.
- **Shared environment rule:** if #125 has already added a "development or test" helper, reuse it; otherwise add a private `isDevelopmentOrTest()` that #125 can reuse.
- **Shared parser:** consider extracting the origin regex into a small shared helper, e.g. a public constant on `TrustedOrigins` or an `OriginParser`, so the validator and the middleware cannot drift apart.

## Tests

`ConfigValidatorTest`:

- valid `https` entries;
- an entry with a path or a trailing `/`;
- no scheme;
- `ftp://`;
- `http` entries under `PHP_ENV` `production`, unset, `development` and `test`;
- mixed entries reporting each kind once;
- failure lines never contain the value.

`bin/check-config` needs no change: it already prints every failure line.

## Files to Change

- `source/lib/Infra/ConfigValidator.php`: `checkAllowedOrigins()`.
- `source/tests/unit/Infra/ConfigValidatorTest.php`
