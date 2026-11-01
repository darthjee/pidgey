# Router, Route and RouteMatch
Implement `routing.md` in `Pidgey\Http\`.

- `Route`: method, pattern, handler class, middleware class names; compiles lazily on first `match` (literal parts `preg_quote`d with `#` as delimiter, `:name` with `[a-zA-Z_][a-zA-Z0-9_]*` → `(?<name>[^/]+)`, anchored `#^…$#`); returns captured params (named groups only) or null. A repeated param name throws `LogicException` at construction.
- `RouteMatch`: readonly handler class, middleware class names and `array<string,string>` params, plus the route pattern (used later by request logging).
- `Router`:
  - `__construct(array $globalMiddlewares = [])` and `globalMiddlewares(): array`;
  - `add(string $method, string $path, string $handlerClass, array $middlewares = [])`: upper-cases the method; `LogicException` when the handler is not `is_subclass_of(RequestHandler::class)`, when a route middleware is also global, or when the same method + pattern (param names normalized, e.g. to `:`) is already registered;
  - `get`/`post`/`patch`/`delete` wrappers;
  - `match(Request $request): ?RouteMatch`: strict registration order, first match wins, method and `path()` both must match, query ignored, case and trailing slash strict.
- The dispatcher (step 05) owns the "no match" `NotFoundException`.
- Unit tests per `routing.md#testing`: compilation including `:id.json` and `/smtp-connections/:id/test.json`, decoded captures, first match wins, duplicate (same pattern with different param names), non-handler class, repeated param and global-duplicate middleware → `LogicException`, wrong method → null, trailing slash and case → null, query ignored, method lower-case at registration.

## Files to Change
- `source/lib/Http/Route.php`, `source/lib/Http/RouteMatch.php`, `source/lib/Http/Router.php` — new.
- `source/tests/unit/Http/RouteTest.php`, `source/tests/unit/Http/RouterTest.php` — new (use `new Request([...])` and a test-only handler subclass, e.g. in `tests/support/`).
