# Request::header() and ForbiddenException

Add the header accessor that `specs/request-handler/request-response/request.md#headers` specifies, and the 403 exception that `request-response/errors.md#exceptions` lists.

## `Request::header(string $name, ?string $default = null): ?string`

- **Source:** reads only `$_SERVER`; never `getallheaders()`.
- **Key mapping:** upper-case the name, replace `-` with `_`, then prefix `HTTP_`. `Content-Type` and `Content-Length` map to `CONTENT_TYPE` and `CONTENT_LENGTH`, with no prefix. `header('CONTENT_TYPE')` hits the same key.
- **Missing vs. empty:** a missing header returns `$default`; an empty one returns `""`.
- **Raw value:** no trimming or splitting.
- **Test overrides:** a new constructor option, `headers` (array of `$_SERVER`-style keys or header names), so tests need no globals. Update the class docblock, which says headers "come later".

## `ForbiddenException`

- **Shape:** `ForbiddenException extends HttpException`, status `403`, default code `FORBIDDEN`. Mirror `NotFoundException`'s constructor (`?string $message`, `?string $code`).
- **Formatter:** check that `ErrorFormatter` renders `FORBIDDEN` for `403` the same way it does `NOT_FOUND` for `404`. Add the mapping if it is missing.

## Tests

- **`RequestTest`:** case-insensitive lookup, the `Content-Type` mapping, missing vs. empty, raw value.
- **`ForbiddenException`:** status, code, message.

## Files to Change

- `source/lib/Http/Request.php`: `header()`, the `headers` option, the docblock.
- `source/lib/Http/ForbiddenException.php`: new.
- `source/lib/Http/ErrorFormatter.php` or `HttpException.php`: only if `403` has no default code yet.
- `source/tests/unit/Http/RequestTest.php`
- `source/tests/unit/Http/ForbiddenExceptionTest.php`: new.
