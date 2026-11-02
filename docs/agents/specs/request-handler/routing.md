# Routing

Purpose: define how a request is mapped to a handler class: route registration, where the route files live, `:param` matching and capture, prefix routes, the GET fallback, and the 404 fallback.

Part of the request flow indexed in [request-handler.md](../request-handler.md). Routing runs after the [`Request`](request-response/request.md) is built and before the hooks ([middlewares.md](middlewares.md)) and the handler ([handlers.md](handlers.md)).

This file is structural only. What each endpoint does stays in [api/endpoints/](../api/endpoints/). API paths follow [api/conventions/format.md](../api/conventions/format.md#paths): root-relative, ending in `.json`, with the single `/v1` exception `POST /v1/messages`. The frontend routes (`GET /`, `GET /assets/*` and the GET fallback) are the other non-`.json` paths; what they serve is in [static-files.md](static-files.md).

## Router

Routes are registered on a **`Router` instance** in `Pidgey\Http\`. There is no router library.

- The front controller ([front-controller.md](front-controller.md#bootstrap)) gets the `Router` from `source/config/routes.php` ([Route files](#route-files)). There is **no global state**: unlike Tent's static `Configuration`, there is nothing to `reset()`. Tests build a fresh `new Router()`.
- **Constructor: `new Router(array $globalMiddlewares = [])`** takes the **global middleware list**, as class names. In v1 it is `[OriginCheck::class]` ([middlewares.md](middlewares.md#global)).
- **`globalMiddlewares(): array`** returns that list unchanged. The dispatcher reads it back and prepends it to the route's list ([front-controller.md](front-controller.md#global-middlewares)), so the list has a single source of truth.
- **`add(string $method, string $path, string $handlerClass, array $middlewares = []): void`** registers one route.
  - `$method` is upper-cased when it is stored.
  - `$handlerClass` must be a subclass of [`RequestHandler`](handlers.md#requesthandler), checked with `is_subclass_of`. Otherwise `add()` throws `LogicException`.
  - `$middlewares` is a list of middleware **class names**. They are built by the [`HandlerFactory`](handlers.md#pidgeyhttphandlerfactory) ([middlewares.md](middlewares.md)) only when the route matches.
  - A middleware class that is also in the global list given to the constructor is rejected with `LogicException` ([middlewares.md](middlewares.md#edge-cases)).
- **Helpers:** `get()`, `post()`, `patch()` and `delete()` take the same arguments without `$method` and are thin wrappers over `add()`.
- **Prefix routes** and the **GET fallback** are registered on the same `Router` ([Prefix routes](#prefix-routes), [GET fallback](#get-fallback)). Their registration API is the implementer's call; the behaviour below is fixed.
- **Duplicates:** registering the same method and the same *pattern* twice throws `LogicException` at registration. Param names don't count: `/a/:id.json` and `/a/:x.json` are the same pattern.
- **Lazy compilation:** a route's regex is compiled the first time the route is tried.
- **No named routes** in v1: nothing needs URL generation or route lookup by name.

Rejected:

- **Static `Configuration` with `reset()`** (Tent): global state that every test must reset.
- **A plain route-table array**: positional tuples, and per-route options become nested arrays.
- **A separate `config/middlewares.php`** or **a global list hard-coded in the dispatcher**: a second source of truth, which the router could not check duplicates against.

## Route files

- `source/config/routes.php` builds the `Router` with the global middleware list (`new Router([OriginCheck::class])`, see [front-controller.md](front-controller.md#global-middlewares)) and requires one file per area, each of which registers its routes on that `Router`:
  - `routes/operational.php`;
  - `routes/auth.php`;
  - `routes/admin.php`;
  - `routes/organization.php`, covering the organization, API tokens and SMTP connections;
  - `routes/v1.php`;
  - `routes/frontend.php`, covering `GET /`, `GET /assets/*` and the GET fallback ([static-files.md](static-files.md#routes)).
- `GET /` and `GET /assets/*` are registered **before** the API routes, and the GET fallback **after every other route**, so it is always tried last. Neither overlaps an API route (API paths end in `.json` or start with `/v1/`), so the order between them and the API only matters for the fallback.
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

## Prefix routes

A **prefix route** matches every path that begins with a literal prefix ending in `/`.

- The frontend's only prefix route is **`GET /assets/*`**: it matches `/assets/` followed by anything, including further `/` (`/assets/fonts/x.woff2`).
- The prefix is `/assets/`, **with the trailing slash**: `/assets` and `/assetsX` are not matched by it. Being extensionless, they get the [GET fallback](#get-fallback)'s `302`. Accepted, no special case.
- The prefix is literal and case-sensitive, matched against the decoded `path()`, like every pattern.
- The router does not check the rest of the path. The handler reads it from `path()` and does its own checks ([static-files.md](static-files.md#path-mapping)).
- A prefix route takes part in [Matching and order](#matching-and-order) like any other route: same method rule, same first-match-wins order.

## GET fallback

The **GET fallback** is a final route, tried after every other route, that sends deep links to the single-page application.

- It applies **only** when all of these hold:
  - the method is `GET`;
  - no other route matched;
  - the path is **extensionless**: its last segment contains no `.`;
  - the path is **outside the `/v1/` prefix**.
- It answers `302 Location: /#<raw REQUEST_URI>`. How the `Location` is built is owned by [static-files.md](static-files.md#redirect).
- Everything else keeps the JSON 404 of [No match](#no-match):
  - unknown `*.json` paths, never a redirect;
  - `GET /v1/*` with no route (e.g. `GET /v1/messages`);
  - paths whose last segment has an extension: `/favicon.ico`, `/robots.txt`, `/index.html`, `/index.php`. Browsers' automatic requests therefore never receive an HTML page; SPA deep links never carry an extension;
  - unmatched non-`GET` requests;
  - `HEAD`, which does not fall back to `GET`.
- Rejected: redirecting `/index.php` to the SPA (the earlier idea in #120); it now gets the JSON 404 like every path with an extension.

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
- Handlers read integer ids with [`intParam()`](handlers.md#route-params-and-intparam), a protected method on `RequestHandler`:
  - a malformed value (not matching `^[1-9][0-9]*$`) gets `400 BAD_REQUEST` — `Validation failed (numeric string is expected)` (`BadRequestException`, see [request-response/errors.md](request-response/errors.md#exceptions)). For example, `GET /smtp-connections/abc.json` gets a 400 from the handler, as in the endpoint specs ([api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md#id-path-parameter));
  - a well-formed value larger than `PHP_INT_MAX` gets the handler's `404` (`NotFoundException` with the resource's not-found message).
- Rejected: **per-param constraints** such as `:id(\d+)`. They add syntax, and the handler must still check that the record exists.

## No match

- A path that matches only with another method gets the **same 404** as an unknown path:
  - status `404`, code `NOT_FOUND`, message `Cannot <METHOD> <path>` (e.g. `Cannot POST /foo.json`, see [api/conventions/errors.md](../api/conventions/errors.md#generic-errors));
  - **no `405`** and no `Allow` header.
- **`HEAD`** does not fall back to `GET`, and **`OPTIONS`** is not answered by the router. Both get a 404 unless a route is registered for them. A CORS preflight never reaches the router: the front controller's CORS step answers it before routing ([front-controller.md](front-controller.md#preflight)). A non-preflight `OPTIONS` is routed normally.
- When nothing matches, the dispatcher throws `NotFoundException('Cannot <METHOD> <path>')`. The error wrapper around the dispatch ([front-controller.md](front-controller.md#error-wrapper)) formats it like any other error.
- The only fallback is the [GET fallback](#get-fallback), for extensionless `GET` paths outside `/v1/`. There is no catch-all fallback handler class for other requests (unlike Tent).
- Whether the router or the dispatcher owns the "no match" exception is the implementer's call. The observable behaviour above is what is fixed.

## Dispatch API

- **`Router::match(Request $request): ?RouteMatch`** returns the matched route and the captured params, or `null` when nothing matches.
  - A `RouteMatch` carries the handler class, the middleware class names and the captured params (`array<string, string>`).
- The dispatcher builds the processing request with the params, so the handler reads them through `param()` ([request-response/processing-request.md](request-response/processing-request.md#route-params)).
- The dispatcher asks the [`HandlerFactory`](handlers.md#pidgeyhttphandlerfactory) to build the handler and the route's middlewares from the `RouteMatch`.

## Edge cases

- **Trailing slash:** strict. `/health.json/` gets a 404.
- **Case:** strict. `/Health.json` gets a 404.
- **Query string:** ignored by matching (see [Matching and order](#matching-and-order)).
- **`/`:** `GET /` serves the frontend's `index.html` ([static-files.md](static-files.md#routes)). It is no longer the health check ([rewrite.md](rewrite.md#transition)). The query string is ignored, so `/?x=1` serves `index.html` too.
- **Unknown `*.json`:** JSON 404, never a redirect.
- **`/assets/*.json`:** matched by the `/assets/` prefix route before the API routes, and answered with the static-file handler's JSON 404 (`json` is not on its allow-list). No API route lives under `/assets/`.
- **`GET /v1/*` with no route** (e.g. `GET /v1/messages`): JSON 404.
- **Unmatched non-`GET`:** JSON 404.
- **`HEAD`:** does not fall back to `GET`, so `HEAD /` gets the JSON 404 ([No match](#no-match)).
- **A last segment with an extension** (`/favicon.ico`, `/robots.txt`, `/index.html`, `/index.php`): JSON 404, not the GET fallback.
- **`/assets` and `/assetsX`:** not matched by the `/assets/` prefix; extensionless, so they get the GET fallback's `302`.
- **Extensionless unknown `GET`** (`/foo`, `/foo/`): `302 Location: /#/foo` (or `/#/foo/`).
- **`/v1/messages`:** a plain literal route with no `.json` ([api/conventions/format.md](../api/conventions/format.md#paths)).
- **`/` inside a param:** never happens. Apache rejects `%2F` before PHP runs ([rewrite.md](rewrite.md#edge-cases)), and `[^/]+` stops at `/`.

## Testing

- **Unit tests** for `Route` and `Router` use `new Request([...])` ([request-response/request.md](request-response/request.md#test-overrides)) and cover:
  - compilation, including `:id.json`;
  - captured params and decoding;
  - first match wins;
  - duplicate and invalid registrations throw `LogicException`;
  - a route middleware that duplicates a global middleware throws `LogicException`;
  - a wrong method gives `null`;
  - trailing slash and case;
  - prefix routes: `/assets/` matches nested paths, `/assets` and `/assetsX` do not;
  - the GET fallback: applied to extensionless `GET` outside `/v1/` only; `*.json`, `/v1/*`, paths with an extension, non-`GET` and `HEAD` are not matched by it.
- A **routes-table test** loads `config/routes.php` and asserts that:
  - every endpoint listed in [api/endpoints/](../api/endpoints/) resolves to its handler;
  - no route is shadowed by an earlier one;
  - `GET /` and `GET /assets/*` resolve to `StaticFileHandler`, and the GET fallback is registered last.

## Performance and security

- Matching is a linear scan over about 35 lazily compiled regexes, which is negligible.
- Matching works on the decoded path only. Routes map to classes, not files. The only handler that reads files is `StaticFileHandler`, which guards against traversal itself ([static-files.md](static-files.md#path-mapping)).
- A decoded `%00` already gets `400` in the `Request` ([request-response/request.md](request-response/request.md#method-path-and-query)).
- Handler and middleware classes are fixed at registration and are never taken from the request.

## Hand-offs

- **#60 (handlers, [handlers.md](handlers.md)) — resolved:**
  - the [`HandlerFactory`](handlers.md#pidgeyhttphandlerfactory) builds the handler class (and the route's middlewares) from the `RouteMatch`;
  - the integer-id helper is [`intParam()`](handlers.md#route-params-and-intparam), a protected method on `RequestHandler` (`400` for a malformed value);
  - the base class that `add()` checks with `is_subclass_of` is [`RequestHandler`](handlers.md#requesthandler).
- **#61 (middlewares, [middlewares.md](middlewares.md)) — resolved:**
  - per-route middleware class names come from `add()` and run after the global ones ([middlewares.md](middlewares.md#attachment));
  - CORS (preflight and headers) is not a middleware but a front-controller step that runs before routing, so a preflight is answered instead of getting a 404 ([middlewares.md](middlewares.md#errors-cors-and-request-logging)).
- **#62 (front controller, [front-controller.md](front-controller.md)) — resolved:**
  - it gets the `Router` from `config/routes.php` at boot ([front-controller.md](front-controller.md#bootstrap));
  - the global middleware list is passed to the `Router` constructor and read back with `globalMiddlewares()` ([front-controller.md](front-controller.md#global-middlewares));
  - the dispatch runs `match()` and the error wrapper formats the `NotFoundException('Cannot <METHOD> <path>')` raised when nothing matches ([front-controller.md](front-controller.md#dispatch-pipeline), [front-controller.md](front-controller.md#error-wrapper));
  - the CORS step answers a preflight before routing and adds headers to every final response ([front-controller.md](front-controller.md#cors-step-pidgeyhttpcors)); the request-logging step logs the final status, including routing `404`s ([front-controller.md](front-controller.md#request-logging-step)).

## Pending decisions

None.
