# Request models

Add `Pidgey\Http\Request` and `Pidgey\Http\ProcessingRequest`, limited to what dispatching needs now.

**`Request`** (`final`, immutable; see `request.md`):

- Constructor: `__construct(array $options = [])`. The supported options are `method` and `uri`. A missing option falls back to the PHP global (`$_SERVER['REQUEST_METHOD']`, `$_SERVER['REQUEST_URI']`), so tests need no web server and never touch `$_SERVER`.
- `method(): string`: upper-cased. Defaults to `GET` when there is neither an option nor a global value.
- `path(): string`: `rawurldecode(parse_url($uri, PHP_URL_PATH))`. Returns `/` when the URI is missing, can't be parsed, or has no path. `+` stays as is.
- `query(): string`: the raw query string, `''` when there is none.

**`ProcessingRequest`** (`final`; see `processing-request.md`):

- Constructor: `__construct(Request $request)`.
- `request(): Request`, so later code can reach the wrapped request.
- It delegates `method()`, `path()` and `query()` to the request unchanged.
- Early response:
  - `respond(Response $response): void` throws `LogicException` when a response is already set;
  - `hasResponse(): bool`;
  - `response(): ?Response`.
- Route params and attachments are left out.

Tests:

- `RequestTest`:
  - the method is upper-cased (`'post'` → `POST`) and has a default;
  - the path is decoded (`%20` → space, `%2B` → `+`, `+` kept), keeps no query, and is `/` for missing or unparsable URIs (e.g. `'http:///'`);
  - the query is present or `''`.
  - Only touch `$_SERVER` in a test that backs it up and restores it, if a fallback test is needed at all.
- `ProcessingRequestTest`:
  - it delegates;
  - `hasResponse()`/`response()` before and after `respond()`;
  - a second `respond()` throws `LogicException`.

## Files to Change

- `source/lib/Http/Request.php` — new.
- `source/lib/Http/ProcessingRequest.php` — new.
- `source/tests/unit/Http/RequestTest.php` — new.
- `source/tests/unit/Http/ProcessingRequestTest.php` — new.
