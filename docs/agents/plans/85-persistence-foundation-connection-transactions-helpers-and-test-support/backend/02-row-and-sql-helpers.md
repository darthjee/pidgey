# Row and Sql conversion helpers
Add `Pidgey\Model\Row`, a final class of static helpers that read a typed value from a `FETCH_ASSOC` row by column name, as specified in `persistence/entities.md#fromrow`:

- `int()`, `string()`, `bool()` (`0`/`1` → bool), `dateTime()` (`Y-m-d H:i:s` parsed in UTC → `DateTimeImmutable`), `enum(array $row, string $column, class-string<BackedEnum> $enum)` (`::from()`), `stringList()` (`json_decode` with `JSON_THROW_ON_ERROR`, checked to be a list of strings).
- Nullable variants (`nullableInt()`, `nullableString()`, `nullableDateTime()`, `nullableEnum()`, …) returning `null` for `null`.
- A missing column throws `LogicException` naming the column; a bad value (unparseable date, unknown enum value, invalid JSON, wrong type) throws naming the column and the expected type. **Never include the value in any message.**

Add `Pidgey\Repository\Sql::dateTime(DateTimeImmutable): string`, converting to UTC first and formatting `Y-m-d H:i:s`.

Tests (unit, no DB): every conversion; nullable variants; UTC parsing; non-UTC input to `Sql::dateTime()` converted; missing column; unknown enum; invalid JSON; non-list JSON; and an assertion that a secret-looking value placed in the row never appears in any thrown message.

## Files to Change
- `source/lib/Model/Row.php` — new.
- `source/lib/Repository/Sql.php` — new.
- `source/tests/unit/Model/RowTest.php` — new (with a small test-only backed enum in the test file or `tests/support/`).
- `source/tests/unit/Repository/SqlTest.php` — new.
