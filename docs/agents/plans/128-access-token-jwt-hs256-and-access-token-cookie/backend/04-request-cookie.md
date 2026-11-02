# Request::cookie()
Implement `request-handler/request-response/request.md#cookies`.

- `Request` gains a `cookies` constructor option (`array<string, mixed>`), defaulting to `$_COOKIE`; keep the class `final readonly`.
- `cookie(string $name, ?string $default = null): ?string`: the value when it is a string; `$default` when the cookie is missing or its value is an array (`access_token[x]=…`), so an array never reaches the JWT code.
- `headers()` still leaves out `cookie`; update the class docblock ("cookies come later").
- Tests in `RequestTest`: present, missing (default and `null`), array value, override through the option, `headers()` without `cookie`.

## Files to Change
- `source/lib/Http/Request.php` — `cookies` option and `cookie()`.
- `source/tests/unit/Http/RequestTest.php` — cookie cases.
