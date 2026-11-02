# `Validator`
Add `Pidgey\Http\Validator` (and its field builder, e.g. `Pidgey\Http\FieldRules`) implementing the contract in [plan.md](../plan.md#shared-contracts):
- `new Validator(array $body)`; `string(string $field): FieldRules`;
- builder rules: `trim()`, `notEmpty()`, `email()`, `minLength(int)`, `maxLength(int)`, `maxBytes(int)`, `noNulByte()`, each returning the builder;
- `validated(): array<string, string>` — evaluates fields in declaration order, returns only declared fields (trimmed values when `trim()` was declared), or throws `ValidationFailedException` with every message.

Per-field evaluation: absent/`null` → `should not be empty` and stop; non-string → `must be a string` and stop; trim if declared; `notEmpty()` and `""` → `should not be empty` and stop; then every other rule in declaration order. `trim()` uses PHP's `trim()` (ASCII whitespace), matching the spec's "surrounding whitespace".

Messages, exactly: `<field> should not be empty`, `<field> must be a string`, `<field> must be an email`, `<field> must be longer than or equal to N characters`, `<field> must be shorter than or equal to N characters`, `<field> must be shorter than or equal to N bytes`, `<field> must not contain a NUL byte`.

No static state; no dependency on the auth layer. A `string()` declared twice for the same field is a `LogicException`.

Tests (`ValidatorTest`): each rule passing and failing with its exact message; absent, `null`, number, array, boolean → exactly one message; whitespace-only trimmed field → `should not be empty`; password-style field (`minLength(8)->maxBytes(72)->noNulByte()`, no trim) keeps surrounding spaces; multi-byte strings for character vs. byte limits (e.g. 72 bytes but fewer characters); multiple failures on one field and on several fields are all reported, in order, and the exception message is the `"; "` join; unknown fields dropped from `validated()`; the register-body example from `endpoints/auth/session.md` passes.

## Files to Change
- `source/lib/Http/Validator.php` — new.
- `source/lib/Http/FieldRules.php` — new (name may differ).
- `source/tests/unit/Http/ValidatorTest.php` — new.
