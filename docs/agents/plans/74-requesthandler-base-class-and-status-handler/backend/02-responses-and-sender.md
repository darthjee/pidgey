# Responses and ResponseSender

Add the response classes and the sender from `response.md`. All of them live in `Pidgey\Http\`.

**`Response`** (not final; `JsonResponse` extends it):

- Constructor: `__construct(string $body = '', int $status = 200, array $headers = [])`. Each header line goes through `addHeader()`.
- `body(): string`.
- `status(): int`.
- `headers(): list<string>`: header lines in insertion order.
- `addHeader(string $line): void`: appends a line. It throws `InvalidArgumentException` when the line contains `\r` or `\n`, which guards against header injection.

**`JsonResponse extends Response`** (`final`):

- Constructor: `__construct(array|JsonSerializable $data, int $status = 200)`.
- Encodes with `json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)`.
- Adds `Content-Type: application/json`.
- No key conversion.

**`ResponseSender`** (`final`):

- Constructor: `__construct(?callable $statusEmitter = null, ?callable $headerEmitter = null)`. Store them as `Closure`s (`Closure::fromCallable` / first-class callable syntax). The defaults are `http_response_code(...)` and `fn (string $line) => header($line, false)`.
- `send(Response $response): void`:
  1. emits the status;
  2. emits each header line in order;
  3. echoes the body.

Tests:

- `ResponseTest`:
  - the defaults (`''`, `200`, no headers);
  - constructor headers keep their order;
  - `addHeader()` appends;
  - CR and LF are each rejected, both through the constructor and through `addHeader()`.
- `JsonResponseTest`:
  - the body is `{"status":"ok"}` for `['status' => 'ok']`;
  - slashes and unicode stay unescaped;
  - the status defaults to 200 and can be set to another value;
  - the `Content-Type` line is present;
  - a `JsonSerializable` value works;
  - invalid UTF-8 throws `JsonException`.
- `ResponseSenderTest`:
  - inject recording closures, capture the body with `ob_start()`/`ob_get_clean()` (or `expectOutputString()`), and assert the status, the header lines in order (repeated names kept) and the body;
  - the default emitters can't be checked meaningfully under the CLI. Construct a sender with defaults only to confirm it builds, or leave that path to manual testing.

## Files to Change

- `source/lib/Http/Response.php` — new.
- `source/lib/Http/JsonResponse.php` — new.
- `source/lib/Http/ResponseSender.php` — new.
- `source/tests/unit/Http/ResponseTest.php` — new.
- `source/tests/unit/Http/JsonResponseTest.php` — new.
- `source/tests/unit/Http/ResponseSenderTest.php` — new.
