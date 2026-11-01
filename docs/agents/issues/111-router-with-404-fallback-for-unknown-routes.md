# Issue: Router with 404 fallback for unknown routes

## Description
Production (release `0.1.0`) answers **every** path with `200 {"status":"ok"}`, because `source/public/index.php` sends every request to `HealthHandler` ("Until the router exists…"). Unknown paths, `/` and wrong methods should get a `404`, as the request-handler specs already require. Found during the first production release (#100) and recorded as a known gap in `docs/agents/deployment/known-gaps.md` (#101).

The behaviour is already specified; this issue implements the minimal slice of it:

- `docs/agents/specs/request-handler/routing.md`: the `Router`, the route files and the `NotFoundException` fallback (`Cannot <METHOD> <path>`).
- `docs/agents/specs/request-handler/front-controller.md`: the dispatch, `/` → `404`, `HEAD` and non-preflight `OPTIONS` → `404`, and errors in the JSON error envelope.
- `docs/agents/specs/request-handler/request-response/errors.md`: `HttpException`, `NotFoundException`, `ErrorResponse` and `ErrorFormatter`.

## Problem
Today the HTTP layer has only `Request`, `ProcessingRequest` (without route params), `RequestHandler`, `BaseMiddleware`, `JsonResponse`, `ResponseSender` and `HealthHandler`. None of the following exist yet: router, exceptions, error formatter, handler factory, front controller, CORS, origin check or logger. So a `200` from `/health.json` after a release proves only that PHP serves the live release.

## Expected Behavior
- `GET /health.json` → `200 {"status":"ok"}` (`specs/api/endpoints/operational.md`).
- Any unregistered method or path, including `/`, `HEAD` and `OPTIONS` → `404`, code `NOT_FOUND`, message `Cannot <METHOD> <path>`, in the JSON error envelope (`specs/api/conventions/errors.md`). Trailing slash and case are strict. Until the CORS step lands, a preflight `OPTIONS` also gets this `404`.
- Any other uncaught `Throwable` → `500 INTERNAL_ERROR` envelope; no stack trace or message leaks.
- The front controller dispatches through the `Router` built in `source/config/routes.php`, instead of calling `HealthHandler` directly.

## Solution
Minimal slice: only what is needed to route `/health.json` and answer everything else with the `404` envelope.

- **Router** (`Pidgey\Http\Router`, `Route`, `RouteMatch`) per `routing.md`: `add`/`get`/`post`/`patch`/`delete`, `:param` compilation, lazy regex, first match wins, duplicate/invalid registration → `LogicException`, the constructor's global middleware list with `globalMiddlewares()` and the duplicate check, and `match(Request): ?RouteMatch`.
- **Route files:** `source/config/routes.php` builds the `Router` with an **empty** global middleware list (`OriginCheck` is added by its own issue) and requires `routes/operational.php`, which registers `GET /health.json` → `HealthHandler`.
- **Route params:** `ProcessingRequest` takes the captured params and exposes `param()`.
- **Errors:** `HttpException`, `NotFoundException`, `ErrorResponse` and `ErrorFormatter`. An `HttpException` gives its envelope. Any other `Throwable` gives `500 INTERNAL_ERROR` / `Internal server error`, and the real error goes to `error_log()` until Monolog arrives.
- **Dispatch:** `Pidgey\Http\FrontController` with `handle(Request): Response` and `static run()`. `handle()` runs match → processing request with params → handler → response, inside a `try/catch (Throwable)` that calls `ErrorFormatter`. The handler is built directly from the matched class (no `HandlerFactory` yet), and the matched route's middleware classes are built and passed to `handle()`. `public/index.php` becomes the wiring file (autoload, `.env` load, `FrontController::run()`).
- **Tests:** unit tests for `Route`/`Router` (the cases in `routing.md#testing`), for `ErrorFormatter`, and for `FrontController::handle()` with `/`, an unknown path, a wrong method, `HEAD /health.json` and `GET /health.json`.
- **Docs:** remove the "Every path returns `200 {"status":"ok"}`" entry from `docs/agents/deployment/known-gaps.md`.

### Out of scope
These come in later issues, which are not created yet:
- CORS step (`Pidgey\Http\Cors`) and preflight handling.
- `OriginCheck` as the global middleware.
- Request logging and Monolog.
- `HandlerFactory` and a per-request `Container`.
- Boot-time configuration validation and the last-resort `500` fallback.
- Routes for the other areas (auth, admin, organization, v1).

### Notes
- Owner: backend.
- Check after the next release: `curl -s -o /dev/null -w '%{http_code}' https://<domain>/does-not-exist.json` returns `404`.
