# FrontController and index.php
Implement the minimal dispatch of `front-controller.md`.

- `Pidgey\Http\FrontController`:
  - `__construct(Router $router, ErrorFormatter $errorFormatter = new ErrorFormatter())`.
  - `handle(Request $request): Response`: inside `try/catch (Throwable)`: `match()`; null → `throw new NotFoundException('Cannot ' . $request->method() . ' ' . $request->path())`; build `ProcessingRequest` with the params; instantiate the handler class and each middleware class (`globalMiddlewares()` first, then the route's) with `new $class()` (no `HandlerFactory` yet); return `$handler->handle($processing, $middlewares)`. On catch → `$this->errorFormatter->format($e)`. No output.
  - `static run(): void`: inside its own `try/catch (Throwable)`, build `new Request()`, load the router from `config/routes.php`, call `handle()`, then send with `ResponseSender`. The `Request` build is inside the wrapper (it may throw later for `%00`).
- `source/public/index.php`: autoload, `EnvFile::load(__DIR__ . '/../config/.env')` (keep today's behaviour), then `FrontController::run()`. Remove the "Until the router exists" placeholder.
- Unit tests for `handle()` with `new Request([...])` and a real `Router` built in the test: `GET /` → 404 `Cannot GET /`, unknown path → 404, `POST /health.json` → 404 `Cannot POST /health.json`, `HEAD /health.json` → 404, `OPTIONS /health.json` → 404, `GET /health.json` → 200 `{"status":"ok"}`, route params reach the handler, a handler that throws `HttpException` → its envelope, one that throws `RuntimeException` → 500 with no message leaked, middlewares run in global-then-route order.

## Files to Change
- `source/lib/Http/FrontController.php` — new.
- `source/public/index.php` — wire `FrontController::run()`.
- `source/tests/unit/Http/FrontControllerTest.php` — new (plus small test handlers/middlewares in `tests/support/` as needed).
