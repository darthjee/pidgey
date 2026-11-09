# SMTP settings: validation and SessionSettings

Validate the five SMTP transport keys and expose them as `Pidgey\Smtp\SessionSettings`.

- `Pidgey\Infra\AppPath`: `root()` (`dirname(__DIR__, 2)` from `lib/Infra`, i.e.
  `source/`) and `resolve()`.
- `ConfigValidator`:
  - add the three `DEFAULT_SMTP_*_TIMEOUT` constants;
  - add `SMTP_CONNECT_TIMEOUT`, `SMTP_COMMAND_TIMEOUT`, `SMTP_TOTAL_TIMEOUT` to
    `POSITIVE_INTEGERS`;
  - `checkSmtpTimeouts()`: the two `exceeds SMTP_TOTAL_TIMEOUT` rules on effective values,
    skipped for invalid values (compare as digit strings like `checkTokenTtls()`);
  - `checkSmtpCaFile()`: when set, `AppPath::resolve()` then `is_file` and `is_readable`,
    else `SMTP_CA_FILE: not readable`;
  - `checkSmtpEhloName()`: when set, ASCII LDH hostname (labels 1–63, no leading or trailing
    hyphen, total ≤ 253), else `SMTP_EHLO_NAME: invalid hostname`;
  - wire the three checks into `validate()` (not into `validateSmtpEncryption()`).
- `Pidgey\Smtp\SessionSettings`: readonly value object per the contract.
- `Container::smtpSessionSettings()`: memoized; `Env::integer()` with the defaults, cast to
  float; `SMTP_CA_FILE` through `AppPath::resolve()` or null; `SMTP_EHLO_NAME` or null.

Tests:

- `ConfigValidatorTest`: each failure line; unset values pass; defaults applied in the
  comparison (`SMTP_TOTAL_TIMEOUT=5` alone fails on connect and command); an invalid timeout
  reports only `not a positive integer`; an invalid total skips both `exceeds`; relative and
  absolute CA paths; EHLO name boundaries (63 / 64-char label, 253 / 254 total, hyphens,
  non-ASCII, IP literal rejected).
- `CheckConfigTest` (integration): one failing SMTP key exits non-zero without echoing the
  value.
- `tests/unit/Infra/AppPathTest.php`, `ContainerTest` (`smtpSessionSettings()` defaults,
  env values, memoization, relative CA resolved to an absolute path).

## Files to Change

- `source/lib/Infra/AppPath.php` — new.
- `source/lib/Infra/ConfigValidator.php` — constants, keys and checks.
- `source/lib/Smtp/SessionSettings.php` — new.
- `source/lib/Container.php` — `smtpSessionSettings()`.
- `source/tests/unit/Infra/ConfigValidatorTest.php`, `AppPathTest.php`,
  `source/tests/unit/ContainerTest.php`, `source/tests/integration/Infra/CheckConfigTest.php`.
