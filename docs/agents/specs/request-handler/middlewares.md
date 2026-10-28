# Middlewares

Purpose: define the middleware contract (request and response hooks), how middlewares are attached globally and per route, their order, and the concrete v1 middlewares by name and order only.

Part of the request flow indexed in [request-handler.md](../request-handler.md). Middlewares run after routing ([routing.md](routing.md)): their request hooks run before the handler ([handlers.md](handlers.md)), their response hooks after it.

This file is structural only. What each guard checks and answers stays in the api specs ([api/conventions/origin-check.md](../api/conventions/origin-check.md), [api/authentication/gate.md](../api/authentication/gate.md), [api/authentication/api-tokens.md](../api/authentication/api-tokens.md)). The processing request, the responses and the exceptions are used here, not redefined ([request-response.md](request-response.md)). Classes are hand-written: no middleware library and no new runtime dependency ([architecture/dependencies.md](../architecture/dependencies.md)).

## `Pidgey\Http\Middleware`

The interface every middleware implements (Tent-style request and response hooks):

- **`public static function build(Container $container): static`** — the same factory convention as handlers ([handlers.md](handlers.md#buildcontainer)). A middleware with dependencies takes them from the container here; its constructor stays fully typed.
- **`processRequest(ProcessingRequest $request): void`** — the request hook. It may:
  - attach data to the processing request (`attach()`, see [request-response/processing-request.md](request-response/processing-request.md#attached-data));
  - call `respond()` for an early, **non-error** answer ([Early answer](#early-answer));
  - throw, for any error ([Security](#security)).
- **`processResponse(ProcessingRequest $request, Response $response): Response`** — the response hook. It returns the response, either the one it received (possibly with added headers) or a replacement.

Who runs and who builds them:

- **Runs:** `RequestHandler::handle()` runs the request hooks, then the handler (or the early response), then all response hooks ([handlers.md](handlers.md#requesthandler)).
- **Builds:** `HandlerFactory::middlewares()` calls `build()` on each class, in order, and throws `LogicException` for a class that is not a middleware ([handlers.md](handlers.md#pidgeyhttphandlerfactory)).

Illustrative only:

```php
namespace Pidgey\Http;

interface Middleware
{
    public static function build(Container $container): static;

    public function processRequest(ProcessingRequest $request): void;

    public function processResponse(ProcessingRequest $request, Response $response): Response;
}
```

## `Pidgey\Http\BaseMiddleware`

An **abstract** class implementing `Middleware`:

- both hooks are no-ops (`processRequest()` does nothing, `processResponse()` returns the response unchanged);
- `build()` returns `new static()`.

Most guards extend it and override only `processRequest()`. A middleware with constructor arguments must override `build()`, as for handlers.

```php
abstract class BaseMiddleware implements Middleware
{
    public static function build(Container $container): static
    {
        return new static();
    }

    public function processRequest(ProcessingRequest $request): void
    {
    }

    public function processResponse(ProcessingRequest $request, Response $response): Response
    {
        return $response;
    }
}
```

## Rejected alternatives

- **The onion `$next` model** (`process($request, $next)`): each middleware controls whether and when the rest runs, which makes the order and the early-answer rules harder to read and test. The Tent-style hooks keep the loop in one place, `RequestHandler::handle()`.
- **Separate `RequestHook` / `ResponseHook` interfaces:** two interfaces and two lists for what is almost always one class; `BaseMiddleware` already gives no-op defaults.
- **Route groups:** shared middleware lists are plain PHP arrays in the route files ([routing.md](routing.md#route-files)); no extra router concept is needed.
- **Middlewares declared on the handler class:** the route table would no longer show what protects a route, and the deny-by-default test ([Testing](#testing)) would have to inspect handler classes.

## Early answer

The early-response contract is owned by [request-response/processing-request.md](request-response/processing-request.md#early-response) and is not redefined here. In short: after a request hook calls `respond()`, no later request hook runs, the handler is not called, and **all response hooks run**, in registration order, also after an early answer.

No pending item remains: the former question "do response hooks run after an early answer?" was decided in #58.

## Errors, CORS and request logging

### Error responses skip the response hooks

Error responses built from exceptions **do not** go through the response hooks. Any exception (from a request hook, the handler or a response hook) propagates out of `handle()`, and the front controller turns it into an `ErrorResponse` through the single `ErrorFormatter` ([request-response/errors.md](request-response/errors.md#errorformatter), [front-controller.md](front-controller.md)).

### CORS is a front-controller step

CORS (response headers and the `OPTIONS` preflight) is **not a middleware**. It is a front-controller step (#62) that:

- runs **before routing**, so a preflight is answered instead of getting the routing `404` ([routing.md](routing.md#no-match));
- applies to **every final response**, errors included, so a cross-origin admin UI (`ALLOWED_ORIGINS`, see [api/conventions/origin-check.md](../api/conventions/origin-check.md#trusted-origins-and-cors)) can read `401`s and other errors.

The body and status of the gate's uniform `401` stay byte-for-byte identical ([api/authentication/gate.md](../api/authentication/gate.md#uniform-401)); the CORS step only adds headers, the same for every answer of a given request.

### Request logging is a front-controller step

Request logging is a front-controller step (#62), **not a middleware**: it logs the **final** status, including errors and routing `404`s, which a response hook never sees. Redaction follows [request-response/request.md](request-response/request.md) and [api/authentication.md](../api/authentication.md).

### Rejected

- **Re-running the global response hooks on error responses:** couples the error path to the middleware list, and the preflight (which never reaches the hooks, because routing runs first) would still be unsolved.

## Attachment

### Global

Global middlewares run on **every** routed request, **first**. In v1 there is one: **`OriginCheck`** ([api/conventions/origin-check.md](../api/conventions/origin-check.md)).

Global middlewares run after routing, so a cross-site `POST` to an unknown path gets `404`, not `403`. This is accepted: no handler runs and nothing changes state.

### Per route

Each route lists its middleware **class names** in `Router::add()` (and the `get()` / `post()` / ... helpers), see [routing.md](routing.md#router). They are built only when the route matches.

### Merge rule

The list passed to `handle()` is the global middlewares first, then the route's list **in declared order**. The dispatcher builds and concatenates them ([handlers.md](handlers.md#pidgeyhttphandlerfactory)); the handler never knows which hooks are global.

## Concrete middlewares (name and order only)

Behaviour, statuses and messages are defined by the linked specs; this table only fixes names, attachment and order.

| Order | Middleware | Attached | On failure | Defined in |
| --- | --- | --- | --- | --- |
| 1 | `OriginCheck` | Global | `403` | [api/conventions/origin-check.md](../api/conventions/origin-check.md) |
| 2 | `Gate` | Per route: cookie surface (non-public routes) | `401` | [api/authentication/gate.md](../api/authentication/gate.md#the-gate) |
| 2 | `ApiTokenAuth` | Per route: `/v1` | `401` | [api/authentication/api-tokens.md](../api/authentication/api-tokens.md#checks) |
| 3 | `RequireSendScope` | Per route: `/v1`, after `ApiTokenAuth` | `403` | [api/authentication/api-tokens.md](../api/authentication/api-tokens.md#scopes) |
| 3 | `AdminCheck` | Per route: admin routes, after `Gate` | `403` | [api/authentication/gate.md](../api/authentication/gate.md#admin-check) |

- A route uses either `Gate` or `ApiTokenAuth`, never both ([api/authentication/api-tokens.md](../api/authentication/api-tokens.md#disjoint-credentials)).
- **`AdminCheck`** reads the gate's user with `$request->require(...)`. `AdminCheck` without `Gate` is a **misconfiguration**: `require()` throws `LogicException`, which answers `500` ([request-response/processing-request.md](request-response/processing-request.md#attached-data)), never `401` or `403`.

### Scopes

Scope checks use an abstract **`RequireScope`** middleware with a **`SCOPE`** constant, and **one subclass per scope**. In v1 there is one: `RequireSendScope` (`send`). `RequireScope` reads the attached API token with `require()`, so it too must follow `ApiTokenAuth`.

```php
final class RequireSendScope extends RequireScope
{
    protected const SCOPE = 'send';
}
```

```php
$router->post('/v1/messages', SendMessageHandler::class, [ApiTokenAuth::class, RequireSendScope::class]);
```

Illustrative only; the class names of handlers are not fixed here.

**Rejected:** checking the scope in the handler. The route table would not show the requirement, and the guard order (scope before body validation) would rely on each handler.

## Order

Global first, then the route's list in declared order. Route lists follow the guard order of [api/conventions/format.md](../api/conventions/format.md#guard-order):

1. origin check (`OriginCheck`, global);
2. gate (`Gate`) or Bearer token plus scope (`ApiTokenAuth`, `RequireSendScope`);
3. admin check (`AdminCheck`);
4. body validation, **inside the handler** ([handlers.md](handlers.md#body-validation)), not a middleware;
5. handler.

## `Caller` interface

**`Pidgey\Auth\Caller`** has one method, **`organizationId(): int`**.

- It lives outside `Pidgey\Http\`, which stays free of auth classes ([request-response/processing-request.md](request-response/processing-request.md#attached-data)).
- It is implemented by the authenticated user (attached by `Gate`) and by the API token (attached by `ApiTokenAuth`).
- Credentials are disjoint, so **at most one** `Caller` is attached per request; `attached()` / `require()` never see two matches.
- Organization-scoped handlers use `$request->require(Caller::class)` ([organizations/scope.md](../organizations/scope.md)). On a route with neither guard this throws `LogicException` → `500`.

## Security

- **Guards throw** (`UnauthorizedException`, `ForbiddenException`, see [request-response/errors.md](request-response/errors.md#exceptions)) and **never** call `respond()`. Every error goes through the single error formatter, which keeps the gate's uniform `401` identical.
- **Hooks never catch exceptions.** A hook must not swallow an exception from the processing request or its own dependencies.
- **Response hooks are infallible in practice** (headers only). A response hook that throws after the handler committed a side effect would turn a done action into a `500` ([handlers.md](handlers.md#edge-cases)).
- **The gate ignores Bearer headers; `ApiTokenAuth` ignores the cookie** ([api/authentication/api-tokens.md](../api/authentication/api-tokens.md#disjoint-credentials)).
- Middleware classes come only from route registration and the global list, never from the request.

## Edge cases

1. **A request hook throws:** later request hooks and the handler are skipped, and **no** response hooks run. The exception propagates to the front controller's error wrapper.
2. **A middleware class in both the global list and a route's list:** `Router::add()` rejects it with `LogicException`. See [Pending decisions](#pending-decisions) for how the router learns the global list.
3. **A route with an empty list:** gets only the global middlewares.
4. **A response hook replaces the response:** allowed. The last hook's return value is sent.

## Testing

For the implementation issues:

- **Unit tests per middleware** with a real `ProcessingRequest` built from `Request` overrides ([request-response/request.md](request-response/request.md#test-overrides)), plus fakes for the middleware's dependencies.
- **Deny-by-default integration test** over `config/routes.php`:
  - every route outside an explicit **public allowlist** has `Gate` or `ApiTokenAuth`;
  - `AdminCheck` appears only after `Gate`.

## Hand-offs

- **#62 (front controller, [front-controller.md](front-controller.md)):**
  - the **CORS step**: answers the preflight before routing and adds the CORS headers to every final response, errors included;
  - the **request-logging step**: logs the final status of every request, including errors and routing `404`s, with redaction per [request-response/request.md](request-response/request.md) and [api/authentication.md](../api/authentication.md);
  - the global middleware list (`OriginCheck`) is built and prepended by the dispatcher ([handlers.md](handlers.md#pidgeyhttphandlerfactory)).

## Pending decisions

- **Where the global middleware list comes from, so `Router::add()` can reject a duplicate (edge case 2):** today the `Router` only receives per-route lists ([routing.md](routing.md#router)) and the dispatcher prepends the global list ([handlers.md](handlers.md#pidgeyhttphandlerfactory)), so the router has no access to the global list. The rule ("`add()` rejects a class that is also global, with `LogicException`") is fixed; how the router learns the global list is not.
