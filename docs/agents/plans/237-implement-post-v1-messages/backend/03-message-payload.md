# Message payload validation

Validate the JSON body into a `Pidgey\Email\Message` using the existing `Validator`, following `email/message-scope.md`.

| Field | Rules |
| --- | --- |
| `to` | required string, trimmed, not empty, ASCII-only email, `maxLength(255)`, no line breaks |
| `subject` | required string, no control characters (CR, LF and TAB included), at most **255 UTF-8 characters** |
| `text` | optional string |
| `html` | optional string |

- At least one of `text` and `html` is required.
- Failures answer `400 VALIDATION_FAILED` with the documented `details` messages, such as `subject must be shorter than or equal to 255 characters` and `subject must not contain control characters`.
- Malformed JSON, a `415` content type and a `413` body are already handled by `Request::json()`.

Reuse the rule style of `SmtpConnectionRules`/`ApiTokenRules`, in a `MessageRules` class or similar. Check that `Validator`'s `maxLength` counts UTF-8 characters, not bytes. If it counts bytes, fix that or use a character-based rule.

**Tests (unit):**
- a `subject` of exactly 255 multibyte characters is accepted, and 256 are rejected;
- a missing `to` or `subject` is rejected;
- a payload with neither `text` nor `html` is rejected;
- control characters in `subject` are rejected;
- an invalid or non-ASCII `to` is rejected.

## Files to Change
- `source/lib/Http/MessageRules.php` (or `MessagePayload.php`): new, builds a validated `Message`.
- `source/tests/unit/Http/MessageRulesTest.php`: new.
