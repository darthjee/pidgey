# Issue: Specs: request-handler — front controller (index.php)

## Description

Write `docs/agents/specs/request-handler/front-controller.md`, the last part of Epic #55. It specifies what `source/public/index.php` does: bootstrap, the dispatch pipeline, the CORS and request-logging steps, and the error handling around the whole dispatch. It ties together the request-response (#58), routing (#59), handlers (#60) and middlewares (#61) specs.

```
Apache + .htaccess → source/public/index.php → Request → CORS preflight → routing → middlewares (pre hooks) → request handler → middlewares (post hooks) → errors → ErrorFormatter → CORS headers, request logging → ResponseSender
```

Reference: `/Users/darthjee/messages/php-request-handler/front-controller.md` (Tent's `index.php` and `RequestProcessor`).

## Problem

- `front-controller.md` is a placeholder (**Pending decision**: "content to be specified in #62").
- The sibling specs left hand-offs to #62:
  - **routing.md:** build the `Router` from `config/routes.php`; format `NotFoundException('Cannot <METHOD> <path>')`.
  - **handlers.md:** one `Container` per request; the dispatcher uses `HandlerFactory` and concatenates global, then route middlewares; `ErrorFormatter` wraps the dispatch.
  - **middlewares.md:** the CORS step (preflight before routing, headers on every final response, errors included), the request-logging step (final status, including errors and `404`s), and the source of the global middleware list (`OriginCheck`).
  - **middlewares.md / routing.md pending item:** how `Router::add()` learns the global list so it can reject a duplicate.
- The current `source/public/index.php` is a placeholder answering a static `{"status":"ok"}` on every path.

## Expected Behavior

- `front-controller.md` is the source of truth for bootstrap, dispatch, CORS, request logging and error handling, with no **Pending decision** left on these topics.
- The middlewares/routing pending item on the global middleware list is resolved, and the sibling specs point to `front-controller.md` instead of "#62".
- The rules of the Epic hold: spec only, structure only, `docs/agents/specs.md` conventions, English.

## Solution

### Scope
- **In scope:** `docs/agents/specs/request-handler/front-controller.md`; sibling updates to resolve the hand-offs: `routing.md` and `middlewares.md` (global middleware list pending item, below), `handlers.md`, `request-response.md` / `request-response/errors.md` (wording only), and the index `request-handler.md`.
- **Out of scope:** PHP, `.htaccess` and tests; guard internals; endpoint behaviour; the log-level variable (still pending in `architecture/configuration.md`); the production hosting target (oak / mod_fcgid).

### Testable class and minimal `index.php`
- `Pidgey\Http\FrontController` holds the logic:
  - `handle(Request): Response` — the whole pipeline, no output, unit-testable;
  - `static run(): void` — builds the `Request` from the PHP globals **inside** the error wrapper, calls the pipeline and sends the response.
- `source/public/index.php` is about three lines: Composer autoload, then `FrontController::run()`.
- **Rejected:** dispatch logic in `index.php`; Tent's static `RequestProcessor` / `Configuration`.

### Bootstrap order
1. Composer autoload (PSR-4 `Pidgey\` → `source/lib/`).
2. Logger (Monolog) first, so boot failures can be logged.
3. Configuration from the environment (`architecture/configuration.md`), validated eagerly on every request.
4. `Router` built from `source/config/routes.php`.
5. One `Container` per request (`handlers.md`). PDO stays lazy, so `/health.json` never touches the database.

### Dispatch pipeline
1. Build the `Request` (may throw `BadRequestException` on a decoded `%00`, so it runs inside the wrapper).
2. **CORS step, preflight part:** answers a preflight before routing.
3. `Router::match()`; no match → `NotFoundException('Cannot <METHOD> <path>')`.
4. Build the processing request with the captured params.
5. `HandlerFactory` builds the handler and the middlewares: global list first, then the route's list.
6. `RequestHandler::handle()`.
7. Any `Throwable` from steps 1–6 → `ErrorFormatter::format()` → `ErrorResponse`.
8. **CORS step, headers part:** adds the CORS headers to the final response, errors included.
9. **Request-logging step** on the final response.
10. `ResponseSender::send()`.

### Error wrapper scope
- One `try/catch (Throwable)` around steps 1–6. CORS headers, logging and sending run after it, on the final response.
- **Rejected:** an error-handling middleware (hooks can't wrap the handler, and error responses skip the response hooks, per #61).

### Global middleware list (resolves the pending item in `middlewares.md`)
- The `Router` constructor takes it: `new Router(array $globalMiddlewares)`. `source/config/routes.php` declares `[OriginCheck::class]` when it builds the router.
- `Router::add()` checks route middlewares against it and throws `LogicException` on a duplicate (edge case 2 of #61).
- The dispatcher reads it back with `Router::globalMiddlewares()` and prepends it to the route's list. One source of truth, no new config file.
- **Rejected:** a separate `config/middlewares.php`; a list hard-coded in the dispatcher.

### CORS step contract
- Lives in its own class, `Pidgey\Http\Cors`, unit-tested on its own; applies the rules of `api/conventions/origin-check.md#trusted-origins-and-cors` (exact-match echo of the trusted origin, `Vary: Origin`, credentialed, never `*`).
- **Preflight** = `OPTIONS` with both `Origin` and `Access-Control-Request-Method`. It answers `204` before routing, for any path:
  - trusted origin → `204` with `Access-Control-Allow-Origin`, `Access-Control-Allow-Credentials: true`, `Access-Control-Allow-Methods`, `Access-Control-Allow-Headers`, `Vary: Origin`;
  - untrusted origin (or CORS off) → `204` without CORS headers (the browser blocks the real request).
- Allowed methods and headers are a fixed list: `GET, POST, PATCH, DELETE`; `Content-Type`.
- A non-preflight `OPTIONS` goes to routing (and gets `404` unless registered).
- Every final response (success, early answer or error) gets the CORS headers when the request `Origin` is trusted, plus `Vary: Origin`.

### Request-logging step
- One line per request at `info`: method, path (no query string), final status, duration (monotonic `Pidgey\Infra\Clock`), matched route pattern when any.
- Never logged: headers, cookies, body, query string (redaction per `request-response/request.md` and `api/authentication.md`).
- A `500` additionally logs the real exception at `error` (`request-response/errors.md`).

### Failures outside the wrapper
- **Invalid configuration at boot** (e.g. a `SECRET_KEY` that "refuses boot"): logged, answered with a `500 INTERNAL_ERROR` envelope.
- **Logger, `ErrorFormatter` or sender failure:** last-resort fallback: a hard-coded minimal `500` JSON body plus `error_log()`; never a PHP HTML error page. `display_errors=0`.

### Edge cases
- `HEAD` → `404` unless registered (`routing.md`).
- Output before `send()` is forbidden: handlers and middlewares never `echo`.
- `/` → `404` (no longer the health check; `rewrite.md#transition`).
- Preflight to `/v1/*` gets the same CORS step (and no CORS headers, as API origins are never trusted).

### Testing strategy
- Unit tests for `FrontController::handle()` with `new Request([...])` and a fake router / factory, covering: routing `404`; `HttpException` → envelope; other `Throwable` → `500` plus an `error` log; CORS headers on errors; preflight answered before routing; exactly one log line per request, including `404` and `500`.
- Unit tests for `Cors`.
- A boot test (invalid configuration → `500` envelope). No web server needed.

### Performance and security
- Per-request cost: building the router with lazily compiled regexes; config validation is cheap.
- No stack trace or exception message of a non-`HttpException` reaches the response.
- Untrusted origins are never echoed.

### Backward compatibility
- Replaces the placeholder `index.php` (static `{"status":"ok"}` on every path). `/health.json` keeps the same body through a real route; `/` changes to `404`, as already noted in `rewrite.md#transition`.

## Benefits

- Closes Epic #55: every step of the request flow has a spec, ready for implementation issues.
- `index.php` stays a three-line wiring file; the pipeline is a unit-testable class.
- CORS and logging see every final response, so a cross-origin admin UI can read errors and every request is logged once.
- No PHP HTML error page or stack trace ever reaches a client.
