# Issue: Specs: request-handler — middlewares

## Description

Write `docs/agents/specs/request-handler/middlewares.md`: the middleware contract (Tent-style request and response hooks), how middlewares are attached globally and per route, their order, and the concrete v1 middlewares by name and order only. Part of Epic #55, which specifies how HTTP requests flow through the Pidgey backend:

```
Apache + .htaccess → source/public/index.php → Request → routing → middlewares (request hooks) → request handler → middlewares (response hooks) → Response → ResponseSender
```

**Rules:** spec only (no PHP, `.htaccess` or tests); structure only (endpoint behavior stays in `docs/agents/specs/api/endpoints/`, guard behavior stays in the api specs); format per `docs/agents/specs.md` (title, one-line `Purpose:`, **Pending decision** markers, English). Classes are hand-written in `Pidgey\Http\`, with no router or middleware library and no new runtime dependency (`architecture/dependencies.md`).

Reference: `/Users/darthjee/messages/php-request-handler/middlewares.md`.

## Problem

`middlewares.md` is a placeholder. Sibling specs already depend on it and left hand-offs:

- `handlers.md` (#60) uses `processRequest(ProcessingRequest): void` and `processResponse(ProcessingRequest, Response): Response` but leaves the interface, its `static build(Container): static`, and the "response hooks infallible in practice" constraint to #61.
- `request-response/processing-request.md` (#58) asks for a `Caller` interface with `organizationId()`, and for a decision on whether error responses go through the response hooks (a cross-origin admin UI would otherwise get `401`s without CORS headers).
- `routing.md` (#59) notes that routing runs before the hooks, so a CORS `OPTIONS` preflight would 404, and that per-route middleware class names come from `add()`.
- The original issue listed two pending items (hook naming; response hooks after an early answer); the second was already decided in #58.

## Expected Behavior

`middlewares.md` specifies the following.

### Interface and naming

- **`Pidgey\Http\Middleware`** (interface):
  - `public static function build(Container $container): static` — same factory convention as handlers;
  - `processRequest(ProcessingRequest $request): void` — may attach data or call `respond()` for an early, non-error answer;
  - `processResponse(ProcessingRequest $request, Response $response): Response` — may return a replaced response.
- **`Pidgey\Http\BaseMiddleware`** (abstract): no-op hooks and `build()` returning `new static()`; most guards override only `processRequest`.
- **Rejected:** the onion `$next` model; separate `RequestHook` / `ResponseHook` interfaces.

### Early answer

No pending item: link to `request-response/processing-request.md#early-response` (all response hooks run, in registration order, also after an early answer) instead of redefining it.

### Error responses, CORS and request logging

- Error responses built from exceptions **do not** go through the response hooks.
- **CORS** (headers and preflight) is **not a middleware**: it is a front-controller step (#62) that runs before routing (a preflight is answered, not 404'd) and on every final response, errors included. The gate's uniform `401` stays byte-for-byte identical.
- **Request logging** is a front-controller step (#62), not a middleware: it logs the final status, including errors and routing 404s. Redaction follows `request-response/request.md` and `api/authentication.md`.
- **Rejected:** re-running the global response hooks on error responses (more coupling, preflight still unsolved).

### Attachment and concrete middlewares (name and order only)

- **Global** (every request, first): `OriginCheck` only (`api/conventions/origin-check.md`). Global middlewares run after routing, so a cross-site `POST` to an unknown path gets `404`, not `403`; accepted.
- **Per route** (class names in `add()`):
  - cookie surface: `Gate`, then `AdminCheck` on admin routes (`api/authentication/gate.md`); `AdminCheck` reads the gate's user with `require()`, so `AdminCheck` without `Gate` is a misconfiguration → `500`;
  - `/v1`: `ApiTokenAuth` (Bearer, `401`), then `RequireSendScope` (`403`) (`api/authentication/api-tokens.md`).
- **Scopes:** an abstract `RequireScope` with a `SCOPE` constant, one subclass per scope. **Rejected:** checking the scope in the handler.
- **Rejected:** route groups; declaring middlewares on the handler class.

### Order

Global first, then the route's list in declared order, following `api/conventions/format.md#guard-order`: origin check → gate / Bearer + scope → admin check → body validation (inside the handler) → handler.

### `Caller` interface

- **`Pidgey\Auth\Caller`** (outside `Pidgey\Http\`, which stays auth-free) with `organizationId(): int`.
- Implemented by the authenticated user and the API token. Credentials are disjoint, so at most one `Caller` is attached; organization-scoped handlers use `$request->require(Caller::class)`.

### Security

- Guards **throw** (`UnauthorizedException`, `ForbiddenException`) and never call `respond()`.
- Hooks never catch exceptions.
- Response hooks are **infallible in practice** (headers only).
- The gate ignores Bearer headers; `ApiTokenAuth` ignores the cookie.

### Testing strategy (for the implementation issues)

- Unit tests per middleware with a real `ProcessingRequest` built from `Request` overrides, plus fakes.
- An integration test over `config/routes.php` asserting **deny by default**: every route outside an explicit public allowlist has `Gate` or `ApiTokenAuth`, and `AdminCheck` appears only after `Gate`.

### Edge cases

1. A request hook throws: later request hooks and the handler are skipped, and no response hooks run.
2. A middleware class in both the global list and a route's list: `add()` rejects it with `LogicException`.
3. A route with an empty list gets only the global middlewares.
4. A response hook replaces the response: allowed.

## Solution

- Write `docs/agents/specs/request-handler/middlewares.md` with the sections above, linking to (not redefining) the guard specs and the early-response contract.
- Update sibling specs:
  - `request-handler/handlers.md` — the hook-signatures section points to `middlewares.md` as the owner;
  - `request-handler/request-response/processing-request.md` — resolve the `Caller` and error-response hand-offs;
  - `request-handler/request-response.md` — resolve the CORS hand-off;
  - `request-handler/routing.md` — the CORS preflight note points to the #62 front-controller step;
  - `request-handler.md` — index row and flow comments.
- Hand-offs to #62 (front controller): the CORS step (before routing and on every final response) and the request-logging step.
- **Out of scope:** guard internals, the CORS and request-logging steps themselves, the front controller, any PHP.
- No backward-compatibility impact: spec only, no code yet. A security review of the spec is advisable.

## Benefits

- One clear contract for hooks that `handlers.md` and the front controller can rely on.
- Deny-by-default routing is testable, and errors (including the uniform `401`) have a single path through the error formatter.
- CORS and logging see every response, including errors and 404s, which a hook-based design could not.
