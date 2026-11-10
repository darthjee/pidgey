# Validator rules

Extend the shared validator with the missing templates of
`docs/agents/specs/api/conventions/validation.md#message-templates`, so this and later admin
endpoints reuse them.

- `Validator::integer($field)` returns a new `IntegerRules` (same declare-once guard as
  `string()`): `optional()` (absent or `null` = omitted), `range(min, max)`. Type check:
  `is_int` only — `587.0`, `587.5`, `"587"`, `true` → `<field> must be an integer`, no
  further rule; `null` on a required field → `<field> should not be empty` like strings.
  `validated()` returns the `int`.
- `FieldRules`: `oneOf(...)`, `hostname()`, `noControlCharacters()` (C0, DEL; for `username`),
  `noLineBreaks()` (CR, LF; for `fromAddress`).
- `Pidgey\Http\HostName::isValid()`: LDH labels 1–63 chars, no leading/trailing hyphen, total
  at most 253, ASCII only, no trailing dot; a value whose labels are all numeric must be a
  valid IPv4 (`filter_var(FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)`); IPv6 bare or `[...]`
  through `FILTER_FLAG_IPV6`, `%` zone ids rejected. Whitespace and control characters fail.
- Unit tests for every rule, message and edge case (`999.1.1.1`, `smtp.example.com.`,
  `fe80::1%eth0`, `smtp.exämple.de`, punycode accepted, 63/64-char labels, 253/254 total,
  `::1`, `[::1]`, `::ffff:10.0.0.1`).

## Files to Change
- `source/lib/Http/Validator.php` — `integer()`, mixed field list in `validated()`.
- `source/lib/Http/IntegerRules.php` — new.
- `source/lib/Http/FieldRules.php` — `oneOf`, `hostname`, `noControlCharacters`, `noLineBreaks`.
- `source/lib/Http/HostName.php` — new.
- `source/tests/unit/Http/ValidatorTest.php`, `IntegerRulesTest.php`, `HostNameTest.php` — tests.
