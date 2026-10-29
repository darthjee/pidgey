# Middleware types and RequestHandler

Add the hook types from `middlewares.md` and the base handler from `handlers.md`. Leave out `build(Container)` (it arrives with the Container issue).

**`Pidgey\Http\Middleware`** (interface):

- `processRequest(ProcessingRequest $request): void`
- `processResponse(ProcessingRequest $request, Response $response): Response`

**`Pidgey\Http\BaseMiddleware`** (abstract, implements `Middleware`): no-op defaults. `processRequest()` does nothing, and `processResponse()` returns `$response` unchanged.

**`Pidgey\Http\RequestHandler`** (abstract):

- `final public function handle(ProcessingRequest $request, array $middlewares): Response`. Document `$middlewares` as `list<Middleware>`.
  1. Run `processRequest()` on each middleware in order, and break right after the first one that leaves `$request->hasResponse()` true.
  2. Set `$response` to `$request->response()` if one is set, otherwise to `$this->process($request)`.
  3. Run `processResponse()` on **every** middleware in the same order, feeding each one's return value into the next, and return the last value.
- `abstract protected function process(ProcessingRequest $request): Response`.
- No `try`/`catch`.

Tests:

- `BaseMiddlewareTest` uses an anonymous subclass: `processRequest()` changes nothing (no response is set), and `processResponse()` returns the same instance.
- `RequestHandlerTest` uses an anonymous `RequestHandler` subclass and recording test-double middlewares, e.g. a small helper class in `tests/support/` (namespace `Pidgey\Tests\Support\`, already autoloaded) that logs `"<name>:request"` / `"<name>:response"` into a shared array. Cases:
  - with no middlewares, `handle()` returns `process()`'s response;
  - request hooks run in order, then `process()`, then response hooks in the same order (not reversed);
  - an early response from the first middleware skips the second middleware's request hook and `process()`, and response hooks still run for **both** middlewares;
  - a response hook that replaces the response: the last hook's return value is returned;
  - an exception thrown from a request hook, from `process()` or from a response hook propagates unchanged.

## Files to Change

- `source/lib/Http/Middleware.php` — new.
- `source/lib/Http/BaseMiddleware.php` — new.
- `source/lib/Http/RequestHandler.php` — new.
- `source/tests/support/RecordingMiddleware.php` — new, a test double (or define the doubles inline in the test file, which phpcs allows under `tests/`).
- `source/tests/unit/Http/BaseMiddlewareTest.php` — new.
- `source/tests/unit/Http/RequestHandlerTest.php` — new.
