# HTTP exceptions
Add the exceptions listed in `request-response/errors.md#exceptions` that the parser and validation need, following the existing `ForbiddenException` / `NotFoundException` pattern:
- `BadRequestException(?string $message = null)` — 400, no specific code;
- `PayloadTooLargeException` — 413, no code, no message;
- `UnsupportedMediaTypeException` — 415, no code, no message;
- `ValidationFailedException(list<string> $details)` — 400, code `VALIDATION_FAILED`, message `implode('; ', $details)`, `details()` returns the list.

Unit tests for each: status, code, message, details. Add one `ErrorFormatterTest` case for `ValidationFailedException` asserting the full envelope (`error.code`, `error.message`, `error.details`, `statusCode` 400), and one each for 413/415 asserting the category code and reason phrase fallback.

## Files to Change
- `source/lib/Http/BadRequestException.php` — new.
- `source/lib/Http/PayloadTooLargeException.php` — new.
- `source/lib/Http/UnsupportedMediaTypeException.php` — new.
- `source/lib/Http/ValidationFailedException.php` — new.
- `source/tests/unit/Http/BadRequestExceptionTest.php`, `PayloadTooLargeExceptionTest.php`, `UnsupportedMediaTypeExceptionTest.php`, `ValidationFailedExceptionTest.php` — new.
- `source/tests/unit/Http/ErrorFormatterTest.php` — envelope cases.
