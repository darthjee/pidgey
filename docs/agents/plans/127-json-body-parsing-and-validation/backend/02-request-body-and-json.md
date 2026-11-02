# `Request` body, JSON parsing and NUL path
Extend `Pidgey\Http\Request` per `request-response/request.md#body`:
- constructor option `body` (string), replacing `php://input`; update the `@param` array shape;
- `public const MAX_BODY_BYTES = 1048576;`
- `body(): string` — reads `php://input` once with `file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1)`, cached;
- `json(): array` — lazy and cached, rules in order:
  1. `body() === ''` → `[]` (whatever the `Content-Type`);
  2. media type: take `header('Content-Type')`, split on `;`, compare the trimmed type to `application/json` ignoring case; any parameter named `charset` must equal `utf-8` ignoring case (quotes allowed or not — pick and test one, e.g. strip surrounding `"`); otherwise `UnsupportedMediaTypeException`;
  3. size: a numeric `Content-Length` above `MAX_BODY_BYTES`, or `strlen(body()) > MAX_BODY_BYTES`, → `PayloadTooLargeException`. Check `Content-Length` **before** calling `body()`;
  4. `json_decode($body, false, 64, JSON_THROW_ON_ERROR)` — decode to objects so `{}` and `[]` can be told apart; `JsonException` → `BadRequestException`;
  5. top level must be a `stdClass`, otherwise `BadRequestException`; then convert to an array (`json_decode` again with `true`, or a recursive cast) so the return type is `array<string, mixed>` and nested objects are arrays;
  6. unknown fields kept.
- `path()` throws `BadRequestException` when the decoded path contains `"\0"`.

Order note: the spec lists media type before size; keep that order, but the `Content-Length` check must not read the body.

Tests in `RequestTest`: empty body with any/no content type → `[]`; `application/json`, `Application/JSON; charset=UTF-8` → parsed; `text/plain`, missing content type with a non-empty body, `charset=latin1` → 415; `Content-Length` over the limit → 413 without reading; body of `MAX_BODY_BYTES + 1` bytes and no `Content-Length` → 413; malformed JSON, invalid UTF-8, depth > 64 → 400; `[]`, `"x"`, `1`, `true`, `null` → 400; `{}` → `[]`; nested object → nested array; unknown fields kept; result cached (second call same value); `%00` in the path → `BadRequestException`.

## Files to Change
- `source/lib/Http/Request.php` — `body` option, `body()`, `json()`, `MAX_BODY_BYTES`, NUL path rule, docblock update.
- `source/tests/unit/Http/RequestTest.php` — new cases.
- `source/tests/unit/Http/FrontControllerTest.php` — optional: a `/%00` request answers the JSON 400.
