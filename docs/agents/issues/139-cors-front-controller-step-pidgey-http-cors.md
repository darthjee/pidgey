# Issue: CORS front-controller step (`Pidgey\Http\Cors`)

## Description
Spun off from #126 (origin check), part of the auth-API work in epic #120. The CORS front-controller step is specified in `specs/request-handler/front-controller.md#cors-step-pidgeyhttpcors` and `specs/api/conventions/origin-check.md#trusted-origins-and-cors`, but no sub-issue of #120 implemented it. CORS is off by default (the admin UI is served same-origin at `/`), so it matters only for a cross-origin admin UI configured through `ALLOWED_ORIGINS` (or the origin of `FRONTEND_BASE_URL`).

## Problem
`FrontController::handle()` goes straight from the request to routing, and its docblock still lists the CORS step as a later addition. With `ALLOWED_ORIGINS` set, a browser on the trusted origin cannot use the API: preflights get `404` (no `OPTIONS` routes) and responses carry no `Access-Control-Allow-*` headers.

## Expected Behavior
- **Preflight**: an `OPTIONS` request with **both** `Origin` and `Access-Control-Request-Method` is answered `204` with an empty body, before routing, for any path:
  - trusted origin: `Access-Control-Allow-Origin: <matched origin>`, `Access-Control-Allow-Credentials: true`, `Access-Control-Allow-Methods: GET, POST, PATCH, DELETE`, `Access-Control-Allow-Headers: Content-Type`, `Vary: Origin`;
  - untrusted origin, `Origin: null`, or CORS off: `204` without any `Access-Control-*` header.
- A non-preflight `OPTIONS` (missing either header) goes to routing as before (`404` unless a route exists).
- **Every final response** from `handle()` (handler response, early middleware answer, static file, redirect, routing `404`, any error envelope, preflight) gets `Vary: Origin`, plus `Access-Control-Allow-Origin` (the matched origin) and `Access-Control-Allow-Credentials: true` when the request `Origin` is trusted.
- The step only adds headers: status and body never change (the gate's uniform `401` stays byte-for-byte identical apart from those headers).
- Matching is exact (scheme, host, port); `*` is never sent; an arbitrary `Origin` is never reflected.

## Solution
- New `Pidgey\Http\Cors` (in `source/lib/Http/`), built from a `TrustedOrigins` (the helper introduced by #126; `TrustedOrigins::fromEnv()` in production), with two entry points:
  - `preflight(Request): ?Response`: returns the `204` response for a preflight, `null` otherwise;
  - `addHeaders(Request, Response): Response`: adds `Vary: Origin` and, for a trusted origin, the allow-origin and credentials headers, through `Response::addHeader()`.
- `FrontController` takes the `Cors` as a constructor dependency (default from env, injectable in tests). In `handle()`: preflight check first, inside the error wrapper; then routing, as today; then `addHeaders()` on the final response, error or not. Update the class docblock (only request logging remains a later step).
- Failures in `run()` before a `Request` exists (invalid configuration, a `BadRequestException` from building the `Request`) have no `Origin` to read and get no CORS headers. That is acceptable: those are `500`/`400` responses the browser would block anyway.
- `Vary: Origin` is added once; no other code in `source/lib` sets `Vary` today.

### Tests
- Unit tests for `Cors`: preflight with a trusted origin, an untrusted one, `Origin: null`, CORS off (empty list); `OPTIONS` without `Origin` or without `Access-Control-Request-Method` → `null`; headers on a normal response for a trusted origin, an untrusted one, no `Origin` header, and CORS off; status and body unchanged; exact matching (different port or scheme is untrusted).
- `FrontController` tests: a preflight on an unrouted path answers `204` before routing (no handler built, no container created); CORS headers on a successful response, on a routing `404`, on a gate `401` and on a `500`; an untrusted origin gets only `Vary: Origin`.

### Out of scope
- The request-logging step.
- Any change to the trusted-origins rules or to `ALLOWED_ORIGINS` validation (already done by #126).

## Benefits
- Completes the dispatch pipeline described in the front-controller spec.
- Makes the documented cross-origin admin-UI setup (`ALLOWED_ORIGINS`) actually work, without loosening the same-origin default.
