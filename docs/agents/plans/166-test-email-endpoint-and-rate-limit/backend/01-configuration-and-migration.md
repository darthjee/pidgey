# Configuration and migration

Add the two settings and the limit table.

- `Pidgey\Infra\ConfigValidator`:
  - add `DEFAULT_SMTP_TEST_MAX_ATTEMPTS = 10` and `DEFAULT_SMTP_TEST_WINDOW = 3600`;
  - add `SMTP_TEST_MAX_ATTEMPTS` and `SMTP_TEST_WINDOW` to `POSITIVE_INTEGERS`, so an invalid
    value fails with `<NAME>: not a positive integer`, never echoing the value.
- `Pidgey\Container`: expose the effective values (defaults applied), for example
  `smtpTestMaxAttempts(): int` and `smtpTestWindow(): int`. The lease length uses
  `smtpSessionSettings()->totalTimeout + 5`.
- Migration `source/migrations/0011_create_smtp_test_limits.sql`: one `CREATE TABLE` with the
  columns, FK and unique index from the shared contract, in the style of
  `0008_create_smtp_connections.sql`.
- Tests:
  - `ConfigValidator` unit tests for both settings (unset, valid, `0`, negative, non-numeric);
  - extend `SchemaTest` to cover the new table, its unique index and its FK, following the
    existing assertions for other tables.

## Files to Change

- `source/lib/Infra/ConfigValidator.php`: defaults and positive-integer checks.
- `source/lib/Container.php`: the effective settings.
- `source/migrations/0011_create_smtp_test_limits.sql`: new.
- `source/tests/unit/Infra/ConfigValidatorTest.php`: new cases.
- `source/tests/integration/Schema/SchemaTest.php`: the new table.
