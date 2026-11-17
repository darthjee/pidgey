# Add ApiTokenRules

Validate the create body, reporting every failed rule at once, mirroring `SmtpConnectionRules`.

`Pidgey\Http\ApiTokenRules` (final, static):

- Constants: `NAME = 'name'`, `SCOPES = 'scopes'`, `KNOWN_SCOPES = ['send']`, `MAX_CHARACTERS = 255`, plus the messages from the contract: `scopes should not be empty`, `scopes must be an array`, `each value in scopes must be one of the following values: send` (build it from `KNOWN_SCOPES`).
- `forCreate(array $body): array{name: string, scopes: list<string>}`:
  1. `$name = (new FieldRules(self::NAME))->trim()->notEmpty()->maxLength(self::MAX_CHARACTERS)`; collect `$name->errors($body)` (covers missing, non-string, blank and too long; length in characters).
  2. Scopes: missing or not (`is_array` && `array_is_list`) → `scopes must be an array`; empty list → `scopes should not be empty`; any element that is not a string strictly equal to a known scope (no trim, no case folding) → the unknown-scope message, once.
  3. If any error, throw `new ValidationFailedException($errors)` with name errors first.
  4. Return `['name' => $name->value($body), 'scopes' => array_values(array_unique($scopes))]`.
- Extra body keys are ignored.

Tests (unit, `ApiTokenRulesTest`): trimmed `" Moria "` → `Moria`; blank, missing, non-string (`42`, `null`, `[]`) and 256-char names rejected; 255 multibyte characters accepted; scopes missing, `"send"`, `{"0":"send"}`, `[]`, `[1]`, `[null]`, `[["send"]]`, `["admin"]`, `["Send"]`, `[" send "]` rejected with the expected messages; `["send","send"]` → `["send"]`; a bad name and bad scopes together yield two `details` entries.

## Files to Change
- `source/lib/Http/ApiTokenRules.php` — new.
- `source/tests/unit/Http/ApiTokenRulesTest.php` — new.
