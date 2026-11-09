# LOG_LEVEL validation

Add a `checkLogLevel()` rule to `Pidgey\Infra\ConfigValidator`, and call it from
`validate()` (not from `validateSmtpEncryption()`).

- An unset or empty value passes: `Env::get('LOG_LEVEL')` returns `null`.
- Otherwise it checks `in_array($value, LoggerFactory::LEVELS, true)`. On a mismatch it
  returns `['LOG_LEVEL: invalid level']`, never with the value.

`bin/check-config` and the web boot already print or log every `validate()` failure line,
so they need no change.

**Tests:**

- **`tests/unit/Infra/ConfigValidatorTest.php`:**
  - an unset or empty `LOG_LEVEL` passes;
  - each of the 8 levels passes (data provider);
  - `INFO`, ` info` and `warn` each produce exactly `LOG_LEVEL: invalid level`;
  - the value never appears in the output.

  Add `LOG_LEVEL` to the keys the test's `setUp` clears and `tearDown` restores.
- **`tests/unit/Infra/Cli/CheckConfigCommandTest.php`** (or the integration
  `CheckConfigTest`, whichever already covers the per-rule failure lines): one case where an
  invalid `LOG_LEVEL` prints `LOG_LEVEL: invalid level` and exits `1`.

## Files to Change

- `source/lib/Infra/ConfigValidator.php`: the `checkLogLevel()` rule; the class docblock
  already covers "never the value".
- `source/tests/unit/Infra/ConfigValidatorTest.php`: the cases above.
- `source/tests/unit/Infra/Cli/CheckConfigCommandTest.php` or
  `source/tests/integration/Infra/CheckConfigTest.php`: one invalid-level case.
