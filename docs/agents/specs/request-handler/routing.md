# Routing

Purpose: define how a request is mapped to a handler class: route registration, where the route files live, `:param` matching and capture, and the 404 fallback.

Part of the request flow indexed in [request-handler.md](../request-handler.md). Routing runs after the [`Request`](request-response/request.md) is built and before the hooks ([middlewares.md](middlewares.md)) and the handler ([handlers.md](handlers.md)).

This file is structural only. What each endpoint does stays in [api/endpoints/](../api/endpoints/). Paths follow [api/conventions/format.md](../api/conventions/format.md#paths): root-relative, ending in `.json`, with the single `/v1` exception `POST /v1/messages`.

## Router

Routes are registered on a **`Router` instance** in `Pidgey\Http\`. There is no router library.

- The front controller ([front-controller.md](front-controller.md)) builds the `Router`. There is **no global state**: unlike Tent's static `Configuration`, there is nothing to `reset()`. Tests build a fresh `new Router()`.
- **`add(string $method, string $path, string $handlerClass, array $middlewares = []): void`** registers one route.
  - `$method` is upper-cased when it is stored.
  - `$handlerClass` must be a subclass of the handler base class ([handlers.md](handlers.md), #60), checked with `is_subclass_of`. Otherwise `add()` throws `LogicException`.
  - `$middlewares` is a list of middleware **class names**. They are built by the factory ([handlers.md](handlers.md), [middlewares.md](middlewares.md)) only when the route matches.
- **Helpers:** `get()`, `post()`, `patch()` and `delete()` take the same arguments without `$method` and are thin wrappers over `add()`.
- **Duplicates:** registering the same method and the same *pattern* twice throws `LogicException` at registration. Param names don't count: `/a/:id.json` and `/a/:x.json` are the same pattern.
- **Lazy compilation:** a route's regex is compiled the first time the route is tried.
- **No named routes** in v1: nothing needs URL generation or route lookup by name.

Rejected:

- **Static `Configuration` with `reset()`** (Tent): global state that every test must reset.
- **A plain route-table array**: positional tuples, and per-route options become nested arrays.

## Route files

- `source/config/routes.php` is a function that receives the `Router` and requires one file per area:
  - `routes/operational.php`;
  - `routes/auth.php`;
  - `routes/admin.php`;
  - `routes/organization.php`, covering the organization, API tokens and SMTP connections;
  - `routes/v1.php`.
- The exact file split is the implementer's call. The rule is **one file per area, required from a single entry file**.
- Within each file, registration order matters (see [Matching and order](#matching-and-order)).

## Path patterns

A route path is a literal path in which `:name` marks a param.

- **Param name:** `:` followed by a name matching `[a-zA-Z_][a-zA-Z0-9_]*`. The name ends at the first character that does not match.
  - Example: in `/smtp-connections/:id.json` the param is `id` and `.json` is a literal.
  - This fixes Tent's `(:[^\/]+)` split, which reads the name as `id.json` and swallows the suffix.
- **Compilation:**
  - literal parts are escaped with `preg_quote`;
  - each param becomes a named group `(?<name>[^/]+)`;
  - the whole pattern is anchored: `#^…$#`.
  - Example: `/smtp-connections/:id/test.json` → `#^/smtp\-connections/(?<id>[^/]+)/test\.json$#`.
- Matching is **case-sensitive** and runs against [`Request::path()`](request-response/request.md#method-path-and-query), which is already decoded, so captured values arrive decoded.
- A path that **repeats a param name** (e.g. `/a/:id/b/:id.json`) throws `LogicException` at registration.

## Matching and order

- A route matches when **both** the method and the path match.
- Routes are tried in **strict registration order**, and the **first match wins**.
- **Shadowing:** a literal route must be registered before a param route that would match it. For example, `POST /admin/users/search.json` must come before any `POST /admin/users/:id.json`-style route, otherwise `search` is captured as an `id`.
  - The router does **not** reorder routes and does **not** detect shadowing. The routes-table test catches unreachable routes (see [Testing](#testing)).
  - Rejected: auto-ordering literal routes first, and throwing on shadowed routes at registration.
- The **query string is ignored**: only `path()` is matched.

## Param values

- Captured values are read by the handler through `param()` on the processing request ([request-response/processing-request.md](request-response/processing-request.md#route-params)). Handlers never re-parse the path.
- The router only checks `[^/]+`. It does not check the value's shape.
- Handlers reject malformed values by throwing `NotFoundException` ([request-response/errors.md](request-response/errors.md#exceptions)). For example, `GET /smtp-connections/abc.json` gets a 404 from the handler.
  - This conflicts with the endpoint specs, which answer `400 BAD_REQUEST` for a malformed `:id` (see [Pending decisions](#pending-decisions)).
- A small helper reads integer ids: an `intParam('id')`-style method that throws `NotFoundException` when the value is not a positive integer. Where it lives is decided in #60 ([handlers.md](handlers.md)).
- Rejected: **per-param constraints** such as `:id(\d+)`. They add syntax, and the handler must still check that the record exists.

## No match

- A path that matches only with another method gets the **same 404** as an unknown path:
  - status `404`, code `NOT_FOUND`, message `Cannot <METHOD> <path>` (e.g. `Cannot POST /foo.json`, see [api/conventions/errors.md](../api/conventions/errors.md#generic-errors));
  - **no `405`** and no `Allow` header.
- **`HEAD`** does not fall back to `GET`, and **`OPTIONS`** is not answered automatically. Both get a 404 unless a route is registered for them.
- When nothing matches, the dispatcher throws `NotFoundException('Cannot <METHOD> <path>')`. The error wrapper around the dispatch ([front-controller.md](front-controller.md), #62) formats it like any other error. There is **no fallback handler class** (unlike Tent).
- Whether the router or the dispatcher owns the "no match" exception is the implementer's call. The observable behaviour above is what is fixed.

## Dispatch API

- **`Router::match(Request $request): ?RouteMatch`** returns the matched route and the captured params, or `null` when nothing matches.
  - A `RouteMatch` carries the handler class, the middleware class names and the captured params (`array<string, string>`).
- The dispatcher builds the processing request with the params, so the handler reads them through `param()` ([request-response/processing-request.md](request-response/processing-request.md#route-params)).
- The dispatcher asks the factory (#60, [handlers.md](handlers.md)) to build the handler and the route's middlewares from the `RouteMatch`.

## Edge cases

- **Trailing slash:** strict. `/health.json/` gets a 404.
- **Case:** strict. `/Health.json` gets a 404.
- **Query string:** ignored by matching (see [Matching and order](#matching-and-order)).
- **`/`:** a 404 unless a route is registered for it. It is no longer the health check ([rewrite.md](rewrite.md#transition)).
- **`/v1/messages`:** a plain literal route with no `.json` ([api/conventions/format.md](../api/conventions/format.md#paths)).
- **`/` inside a param:** never happens. Apache rejects `%2F` before PHP runs ([rewrite.md](rewrite.md#edge-cases)), and `[^/]+` stops at `/`.

## Testing

- **Unit tests** for `Route` and `Router` use `new Request([...])` ([request-response/request.md](request-response/request.md#test-overrides)) and cover:
  - compilation, including `:id.json`;
  - captured params and decoding;
  - first match wins;
  - duplicate and invalid registrations throw `LogicException`;
  - a wrong method gives `null`;
  - trailing slash and case.
- A **routes-table test** loads `config/routes.php` and asserts that:
  - every endpoint listed in [api/endpoints/](../api/endpoints/) resolves to its handler;
  - no route is shadowed by an earlier one.

## Performance and security

- Matching is a linear scan over about 35 lazily compiled regexes, which is negligible.
- Matching works on the decoded path only. Path traversal can't reach the filesystem, because routes map to classes, not files.
- A decoded `%00` already gets `400` in the `Request` ([request-response/request.md](request-response/request.md#method-path-and-query)).
- Handler and middleware classes are fixed at registration and are never taken from the request.

## Hand-offs

- **#60 (handlers, [handlers.md](handlers.md)):**
  - the handler factory builds the handler class (and the route's middlewares) from the `RouteMatch`;
  - it decides where the integer-id helper lives (a method that throws `NotFoundException` when the value is not a positive integer);
  - it defines the handler base class that `add()` checks with `is_subclass_of`.
- **#61 (middlewares, [middlewares.md](middlewares.md)):**
  - per-route middleware class names come from `add()`;
  - CORS preflight: routing runs before the hooks, so an `OPTIONS` preflight on an API path gets a 404. v1 is same-origin and needs no preflight. If CORS is enabled later ([api/pending.md](../api/pending.md)), it needs either `OPTIONS` routes or a global hook that runs before routing.
- **#62 (front controller, [front-controller.md](front-controller.md)):** it builds the `Router` from `config/routes.php`, runs the dispatch, and formats the `NotFoundException('Cannot <METHOD> <path>')` raised when nothing matches.

## Pending decisions

- **Pending decision — status for a malformed `:id`:** this spec (from #59) says handlers reject a malformed param value with `NotFoundException` (`404`), e.g. `GET /smtp-connections/abc.json`. The endpoint specs still answer `400 BAD_REQUEST` — `Validation failed (numeric string is expected)` for an `:id` that does not match `^[1-9][0-9]*$`, after the gate or admin check ([api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md#id-path-parameter), [api/endpoints/admin-users.md](../api/endpoints/admin-users.md#id-path-parameter)). Either the endpoint specs move to `404`, or the integer-id helper (#60) throws `BadRequestException` instead. Until this is decided, the endpoint specs govern the observable answer per endpoint; the routing rule that the router itself never validates the value is fixed either way.
