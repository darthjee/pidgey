# Backend Plan: CORS front-controller step (`Pidgey\Http\Cors`)

Main plan: [plan.md](plan.md)

## Overview
Implement `specs/request-handler/front-controller.md#cors-step-pidgeyhttpcors` with the rules of `specs/api/conventions/origin-check.md#trusted-origins-and-cors`. The step answers preflights before routing and adds headers to every final response produced by `FrontController::handle()`; it never changes a status or a body.

## Context
- `Pidgey\Http\TrustedOrigins` (from #126) already gives `fromEnv()` and `contains(string)` (exact match, `null` never trusted). Reuse it; do not re-implement origin parsing.
- `OriginCheck` shows the dependency pattern to copy: `?TrustedOrigins $trusted = null` defaulting to `TrustedOrigins::fromEnv()`.
- `Response::addHeader()` appends a header line (rejects CR/LF). Nothing in `source/lib` sets `Vary` today, so a single `Vary: Origin` line is enough.
- `Request::header('Origin')` / `header('Access-Control-Request-Method')` return `null` when not sent.
- `FrontController::handle()` currently does routing → handler inside one `try/catch (Throwable)`; `run()` builds the `Request` and the router and has its own catch for config / `Request` failures.

## Implementation Steps

### Step 1 — `Pidgey\Http\Cors`
Create `source/lib/Http/Cors.php`, `final class Cors`:

- Constants: `ALLOW_METHODS = 'GET, POST, PATCH, DELETE'`, `ALLOW_HEADERS = 'Content-Type'`.
- `__construct(?TrustedOrigins $trusted = null)` → `$trusted ?? TrustedOrigins::fromEnv()`.
- `preflight(Request $request): ?Response`
  - returns `null` unless the method is `OPTIONS` **and** both `Origin` and `Access-Control-Request-Method` headers are present (a header sent empty counts as present? — follow the spec literally: "with both headers"; treat `null` as missing, `''` as present, and an empty `Origin` is simply untrusted);
  - otherwise returns `new Response('', 204)`; when the origin is trusted it carries `Access-Control-Allow-Methods` and `Access-Control-Allow-Headers` (the allow-origin, credentials and `Vary` lines are added by `addHeaders()` in the final step, so they are not duplicated).
- `addHeaders(Request $request, Response $response): Response`
  - always adds `Vary: Origin`;
  - when `Origin` is sent and `TrustedOrigins::contains()` it, adds `Access-Control-Allow-Origin: <that origin>` and `Access-Control-Allow-Credentials: true`;
  - returns the same instance (status and body untouched).
- Docblocks in the project's style (every public method documented, `@return`, `@param`).

Unit tests `source/tests/unit/Http/CorsTest.php` (`#[CoversClass(Cors::class)]`, `#[UsesClass]` for `Request`, `Response`, `TrustedOrigins`; build `TrustedOrigins` directly, no env):
- preflight: trusted origin → 204, empty body, allow-methods/allow-headers lines with the exact values; untrusted origin, `Origin: null`, empty trusted list → 204 with no `Access-Control-*` header;
- not a preflight → `null`: `OPTIONS` without `Origin`, `OPTIONS` without `Access-Control-Request-Method`, `GET`/`POST` with both headers;
- addHeaders: trusted → `Vary`, allow-origin echoing the exact origin, credentials; untrusted, no `Origin`, `Origin: null`, CORS off → only `Vary: Origin`; a different port or scheme of a trusted origin is untrusted; status, body and pre-existing headers unchanged; never `*`;
- default constructor reads `ALLOWED_ORIGINS` (save/restore env like `TrustedOriginsTest`).

### Step 2 — Wire into `FrontController`
- Constructor gains `private readonly Cors $cors` — add it as an optional parameter after `$errorFormatter` (e.g. `?Cors $cors = null` → `new Cors()`), keeping `$containerFactory` usable in existing tests (named arguments or reorder consistently; update every call site).
- `handle()`:
  ```php
  try {
      $response = $this->cors->preflight($request) ?? $this->dispatch($request);
  } catch (Throwable $error) {
      $response = $this->errorFormatter->format($error);
  }
  return $this->cors->addHeaders($request, $response);
  ```
  with the current routing body moved to a private `dispatch(Request): Response`. The preflight therefore runs before routing, builds no container and no handler.
- `run()` is unchanged: failures before a `Request` exists get no CORS headers (decided in the issue).
- Update the class docblock: only request logging remains a later step.

Tests in `source/tests/unit/Http/FrontControllerTest.php` (inject a `Cors` over an explicit `TrustedOrigins`; add `#[UsesClass(Cors::class)]`; also add it to `FrontControllerRunTest` / integration tests whose coverage attributes need it):
- a preflight on an unrouted path → 204 before routing: the container factory is never called (counting closure) and no handler runs;
- a non-preflight `OPTIONS` still gets the routing 404 (with `Vary: Origin`);
- trusted origin → CORS headers on the health check 200, on a routing 404, on a thrown `HttpException` envelope (e.g. `ForbiddenException` / a gate-style 401) and on a 500 — status and body identical to the same request without `Origin`;
- untrusted origin → only `Vary: Origin` added.

## Files to Change
- `source/lib/Http/Cors.php` — new class.
- `source/lib/Http/FrontController.php` — `Cors` dependency, preflight before routing, headers on the final response, docblock.
- `source/tests/unit/Http/CorsTest.php` — new unit tests.
- `source/tests/unit/Http/FrontControllerTest.php` — CORS pipeline tests; `UsesClass`.
- Other tests that construct `FrontController` or run it with strict coverage (`FrontControllerRunTest`, integration handler tests via `SessionEndpointTestCase`) — add `#[UsesClass(Cors::class)]` where PHPUnit's strict coverage requires it.

## CI Checks
- `source`: `composer lint` (CI job: `backend_checks`)
- `source`: `composer coverage` (CI job: `backend_tests`)

## Notes
- No spec change: behaviour follows the spec; the "no CORS headers on `run()`-level failures" choice is recorded in the issue.
- Security-sensitive (credentialed CORS): dispatch the `security` agent to review before the PR is marked ready — check no reflection of untrusted origins, no `*`, `Vary: Origin` always present, `null` origin rejected.
- `Access-Control-Max-Age` and `Access-Control-Expose-Headers` are not in the spec; do not add them.
