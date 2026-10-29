# Issue: RequestHandler base class and status handler

## Description

Part of #72 (Bootstrap a testable request pipeline and CI coverage).

Replace the placeholder in `source/public/index.php` with the first real piece of the request handler: the `Pidgey\Http\RequestHandler` abstract base class that later handlers will extend, the middleware types it runs, and `HealthHandler`. For now every request goes to `HealthHandler`, which answers `200` with the JSON body `{"status":"ok"}` (the same payload as today's placeholder). The goal is a **testable setup**, not the full front controller.

## Expected Behavior

- Every method (including `HEAD` and `OPTIONS`) and every path that reaches `index.php` gets `200`, `Content-Type: application/json`, `{"status":"ok"}`. There are no method checks for now.
- `HealthHandler` touches nothing (no database, no SMTP, no file), as `docs/agents/specs/api/endpoints/operational.md` requires for `GET /health.json`.

## Solution

Follow `docs/agents/specs/request-handler/` and build only what this issue uses:

- **Request models** (`request-response/request.md`, `processing-request.md`): a `Request` built from the PHP globals, with the test overrides the spec defines, and a `ProcessingRequest` with early-response support (`respond()` / `hasResponse()` / `response()`). Implement only the members this issue needs; the rest of those specs comes in later issues.
- **Responses** (`request-response/response.md`):
  - `Response`: status, header lines (`headers(): list<string>`) and body.
  - `JsonResponse extends Response`: adds `Content-Type: application/json`.
  - `ResponseSender::send(Response)`: emits the status and each header line through **injectable** emitters (default `http_response_code()` and `header($line, false)`), then echoes the body.
  - `NoContentResponse` and `SetCookie` are left out.
- **Middleware types** (`middlewares.md`):
  - `Pidgey\Http\Middleware` interface with `processRequest(ProcessingRequest $request): void` and `processResponse(ProcessingRequest $request, Response $response): Response`.
  - `Pidgey\Http\BaseMiddleware`, an abstract class with no-op defaults.
  - No concrete middlewares.
- **`Pidgey\Http\RequestHandler`** (`handlers.md`):
  - `final public function handle(ProcessingRequest $request, array $middlewares): Response`, a template method: it runs the request hooks in order and stops after the first one that sets an early response. Then it uses that early response, or calls `process()` if there is none. Last, it runs **all** response hooks in the same order, even after an early response.
  - `abstract protected function process(ProcessingRequest $request): Response`.
  - It catches nothing; errors propagate.
- **`build(Container)` is skipped** on both `RequestHandler` and `Middleware`. The Container issue adds it.
- **`Pidgey\Http\Handler\HealthHandler`** (namespace from the `handlers.md` example), a final class and the real handler for the future `GET /health.json` route: `process()` returns `new JsonResponse(['status' => 'ok'])` (status 200). When the router arrives, it binds this handler to `/health.json`.
- **`index.php`** builds the `ProcessingRequest` from the globals, calls `(new HealthHandler())->handle($request, [])` and sends the result with `ResponseSender`.

### Out of scope

- The router, concrete middlewares, `HandlerFactory`, `Pidgey\Container` and `build()`, logging, the error wrapper and CORS (from `front-controller.md`). These come in later issues.
- `/ready.json`.
- The `.htaccess` / Apache config, handled by #73.

### Testing

PHPUnit unit tests under `source/tests/unit/`:

- the request models and the responses;
- `ResponseSender`, with fake emitters;
- the `RequestHandler` template method, using test-double middlewares, covering hook order, an early response skipping `process()` and the remaining request hooks, and response hooks running after an early response;
- `BaseMiddleware` defaults;
- `HealthHandler`.

Coverage feeds the Codacy report (#75). `index.php` stays thin and is tested manually.

### Responsible agent

backend
