# Errors: exceptions, ErrorResponse, WallClock, ErrorFormatter
Implement the error side of `request-response/errors.md`, limited to what routing needs.

- `Pidgey\Http\HttpException extends RuntimeException`: constructor `(int $status, ?string $code = null, ?string $message = null, ?array $details = null, ?array $smtp = null)`, with read accessors.
- `Pidgey\Http\NotFoundException extends HttpException`: status `404`, takes `(?string $message = null, ?string $code = null)`.
- `Pidgey\Http\ErrorResponse`: builds the envelope `{"error":{"code","message"[,"details"][,"smtp"]},"statusCode","timestamp"}` with `details`/`smtp` omitted when null and `timestamp` in ISO 8601 UTC with milliseconds (`Y-m-d\TH:i:s.v\Z`). `JsonResponse` is `final`: either make `ErrorResponse` compose a `JsonResponse`-equivalent by extending `Response` with the same JSON flags, or drop `final` from `JsonResponse` so `ErrorResponse extends JsonResponse` as the spec says (preferred, matches the spec).
- `Pidgey\Infra\WallClock` interface (`now(): DateTimeImmutable`, UTC) and `Pidgey\Infra\SystemWallClock`.
- `Pidgey\Http\ErrorFormatter` with `format(Throwable $error): ErrorResponse`, `WallClock` injected (default `SystemWallClock`):
  - `HttpException`: its fields; missing code → category code from `api/conventions/errors.md#category-codes` (at least `404 → NOT_FOUND`, `400 → BAD_REQUEST`, `500 → INTERNAL_ERROR`; include the full table from the spec), else `HTTP_<status>`; missing message → standard reason phrase.
  - Any other `Throwable`: `500 INTERNAL_ERROR` / `Internal server error`; the real error goes to `error_log()` (make the logger injectable, e.g. a callable defaulting to `error_log`, so tests don't write to stderr). Never put the exception message or trace in the body.
- Unit tests: envelope shape and omitted keys, timestamp format with a frozen `WallClock`, `NotFoundException` → 404 envelope, fallback code and message, generic `Throwable` → 500 with the error logged and not leaked.

## Files to Change
- `source/lib/Http/HttpException.php` — new.
- `source/lib/Http/NotFoundException.php` — new.
- `source/lib/Http/ErrorResponse.php` — new.
- `source/lib/Http/JsonResponse.php` — drop `final` if `ErrorResponse` extends it.
- `source/lib/Http/ErrorFormatter.php` — new.
- `source/lib/Infra/WallClock.php`, `source/lib/Infra/SystemWallClock.php` — new.
- `source/tests/unit/Http/ErrorResponseTest.php`, `source/tests/unit/Http/ErrorFormatterTest.php`, `source/tests/unit/Http/HttpExceptionTest.php`, `source/tests/unit/Infra/SystemWallClockTest.php` — new.
