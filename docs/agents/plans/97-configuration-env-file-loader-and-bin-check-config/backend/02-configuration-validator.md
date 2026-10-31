# Configuration validator
Add `Pidgey\Infra\ConfigValidator` (or a similar name). It reads the environment through `Env::get` and returns a list of failure strings, shaped `<CHECK>: <reason>`. It never includes values. It checks every boot-refusing rule in [configuration.md](../../../specs/architecture/configuration.md):

- `SECRET_KEY`: `missing` when unset or empty; `too short` when it is under 32 bytes (`strlen`).
- `PREVIOUS_SECRET_KEYS`, when non-empty: split on `,` and trim each entry. Report `empty entry` for any empty entry and `entry too short` for any entry under 32 bytes, at most once each.
- `DB_HOST`, `DB_NAME`, `DB_USER`: `missing`.
- Positive integers when set (`not a positive integer`): `DB_PORT`, `DB_WAIT_TIMEOUT`, `MIGRATE_LOCK_TIMEOUT`, `ACCESS_TOKEN_TTL`, `REFRESH_TOKEN_TTL`, `AUTHORIZATION_REQUEST_TTL`, `AUTHORIZATION_REQUEST_CREATE_LIMIT`, `AUTHORIZATION_REQUEST_CREATE_WINDOW`, `AUTHORIZATION_REQUEST_MAX_OPEN_PER_USER`, `AUTHORIZATION_REQUEST_AUTHORIZE_MAX_ATTEMPTS`, `AUTHORIZATION_REQUEST_AUTHORIZE_LOCK_TTL`, `PASSWORD_RESET_TOKEN_TTL`, `PASSWORD_RESET_COOLDOWN`, `ACCOUNT_EDIT_MAX_ATTEMPTS`, `ACCOUNT_EDIT_LOCK_TTL`.
  - Check this list against the tables in `configuration.md` at implementation time. Every duration and count there must be covered.
  - The rule is `^[1-9][0-9]*$`.
  - `DB_PORT` is a port, not a duration. Include it only if the spec's rules cover it; otherwise leave it out.
- `TRUSTED_PROXY_HOPS`, when set: `^[0-9]+$`; otherwise `not a non-negative integer`.
- `ACCESS_TOKEN_TTL` must be less than `REFRESH_TOKEN_TTL`, with defaults `900` and `604800`. Only compare them when both are valid integers. Otherwise report `ACCESS_TOKEN_TTL: not less than REFRESH_TOKEN_TTL`.
- Unset or empty optional settings use their defaults and pass.
- The PHP version check is **not** here; it lives in the command (step 03).

Unit tests (`source/tests/unit/Infra/ConfigValidatorTest.php`):

- A valid baseline passes.
- Each rule fails on its own, with the exact failure string, and the output never contains the value.
- Several failures are all reported.
- Set and unset the variables with `putenv` and restore them in `tearDown`. The docker test environment already sets `DB_*`, so save and restore those.

## Files to Change
- `source/lib/Infra/ConfigValidator.php`: new validator.
- `source/tests/unit/Infra/ConfigValidatorTest.php`: tests.
