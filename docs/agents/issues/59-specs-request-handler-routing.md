# Issue: Specs: request-handler — routing

## Description

Write `docs/agents/specs/request-handler/routing.md`: how a request is mapped to a handler class. This covers:

- route registration;
- `:param` matching and capture;
- the 404 fallback;
- where the route files live.

Part of Epic #55, which specifies how HTTP requests flow through the Pidgey backend:

```
Apache + .htaccess → source/public/index.php → Request → routing → middlewares (pre hooks) → request handler → middlewares (post hooks) → Response → ResponseSender
```

The specs live under `docs/agents/specs/request-handler/` and are indexed by `docs/agents/specs/request-handler.md`. Today `routing.md` is a stub that says "content to be specified in #59".

Reference: `/Users/darthjee/messages/php-request-handler/routing.md` (Tent rules, `RequestProcessor` dispatch, the dev-API `Route`).

**Rules for every part of #55:**
- **Spec only.** No PHP, `.htaccess` or tests are written here. Implementation issues come later.
- **Structure only.** What each endpoint does stays in `docs/agents/specs/api/endpoints/`.
- **Format:** follow `docs/agents/specs.md`. The file starts with a title and a one-line `Purpose:`, open questions are marked **Pending decision**, and everything is in English.

**Already decided in #55:**
- Hand-written classes in `Pidgey\Http\`, with no router or middleware library.
- Dev-API-style registration: method + path with `:param` → handler class.
- Ordered routes, first match wins, and a guaranteed 404.
- The router captures the params and passes them to the handler.

## Problem

There is no routing spec. The implementation would otherwise copy Tent and the dev API as they are, which brings in three problems:

- **Global static state.** It needs a `reset()` in tests.
- **A param-name bug.** Tent's `(:[^\/]+)` split reads `/smtp-connections/:id.json` as a param named `id.json` and swallows the `.json` suffix.
- **Handlers re-parse the path.** The dev API's `ShowPersonEndpoint` re-extracts `:id` with its own regex.

The endpoint specs also write path params as `{id}` / `{uuid}`, while the routes use `:id`. Several behaviours are unspecified:

- the wrong method (405 or 404);
- `HEAD` and `OPTIONS`;
- trailing slashes;
- malformed ids;
- shadowed literal routes, such as `/admin/users/search.json` next to `/admin/users/:id/...`.

## Expected Behavior

`routing.md` specifies the following. These decisions were agreed during enhancement.

### Registry shape

- **A `Router` instance**, not Tent's static `Configuration`. The front controller builds it, and there is no global state.
  - Tests build a fresh `new Router()`, so no `reset()` is needed.
  - The "reset for tests" item above is covered by this choice.
- **API:** `add(string $method, string $path, string $handlerClass, array $middlewares = []): void`.
  - The method is upper-cased when it is stored.
  - `$handlerClass` must be a subclass of the handler base class (#60), checked with `is_subclass_of`. Otherwise it throws `LogicException`.
  - `$middlewares` are middleware **class names**. The factory (#60/#61) builds them only when the route matches.
- **Helpers:** `get()`, `post()`, `patch()` and `delete()` are thin wrappers over `add()`.
- **Duplicates:** registering the same method and the same *pattern* twice throws `LogicException` at registration. Param names don't count, so `/a/:id.json` and `/a/:x.json` are the same pattern.
- **Order:** routes are tried in strict registration order, and the first match wins.
  - **Shadowing** is documented: a literal route must be registered before a param route that would match it (e.g. `/admin/users/search.json` before `/admin/users/:id.json`).
  - A routes-table test catches unreachable routes. The router does not reorder routes or detect shadowing.
- **Lazy compilation:** a route's regex is compiled the first time it is tried.
- **No named routes** in v1: nothing needs URL generation or route lookup.
- **Rejected:**
  - Static `Configuration` with `reset()`: global state.
  - A plain route-table array: positional tuples, and per-route options become nested arrays.
  - Auto-ordering literal routes first.
  - Throwing on shadowed routes at registration.

### Where routes live

- `source/config/routes.php` is a function that receives the `Router` and requires one file per area:
  - `routes/operational.php`
  - `routes/auth.php`
  - `routes/admin.php`
  - `routes/organization.php`, covering the organization, API tokens and SMTP connections
  - `routes/v1.php`
- The exact file split is the implementer's call. The rule is one file per area, required from a single entry file.

### Param syntax and compilation

- A param is `:` followed by a name matching `[a-zA-Z_][a-zA-Z0-9_]*`. The name ends at the first other character, so in `/smtp-connections/:id.json` the param is `id` and `.json` is a literal.
  - This fixes Tent's `(:[^\/]+)` split, which would read the name as `id.json`.
- Literal segments are escaped with `preg_quote`. Each param compiles to a named group `(?<name>[^/]+)`.
- The pattern is anchored (`#^…$#`), case-sensitive, and matched against `Request::path()`, which is already decoded, so captured values arrive decoded.
- A path that repeats a param name throws `LogicException` at registration.

### Param value shape

- The router only checks `[^/]+`. Handlers reject malformed values by throwing `NotFoundException`. For example, `GET /smtp-connections/abc.json` gets a 404 from the handler.
- A small helper reads integer ids (e.g. an `intParam('id')`-style method that throws `NotFoundException` when the value is not a positive integer). Where it lives is decided in #60.
- **Rejected:** per-param constraints such as `:id(\d+)`. They add syntax, and the handler must still check that the record exists.

### Wrong method, HEAD and OPTIONS

- The match is on method **and** path. A path that matches only with another method gets the same **404** as an unknown path, with code `NOT_FOUND` and the message `Cannot <METHOD> <path>` (`api/conventions/errors.md`, decided in #58). There is no 405 and no `Allow` header.
- `HEAD` does not fall back to `GET`, and `OPTIONS` is not answered automatically. Both get a 404 unless a route is registered.
- Hand-off to #61: routing runs before the hooks, so a CORS preflight (`OPTIONS`) on an API path gets a 404. v1 is same-origin and needs no preflight. If CORS is enabled later (`api/pending.md`), it needs either `OPTIONS` routes or a global hook that runs before routing.

### Edge cases

- **Trailing slash:** strict. `/health.json/` gets a 404.
- **Case:** strict. `/Health.json` gets a 404.
- **Query string:** ignored by matching, because only `path()` is used.
- **`/`:** a 404 unless a route is registered. It is no longer the health check (`rewrite.md`).
- **`/v1/messages`:** a plain literal route with no `.json`.
- Params never contain `/`: Apache rejects `%2F` (`rewrite.md`), and `[^/]+` stops at `/`.

### Dispatch API

- `Router::match(Request $request): ?RouteMatch` returns the matched route (handler class and middleware classes) and the captured params, or `null`.
- The dispatcher builds the processing request with the params, which are read through `param()` (`request-response/processing-request.md`), and asks the factory (#60) for the handler.
- When nothing matches, the dispatcher throws `NotFoundException('Cannot <METHOD> <path>')`. The error wrapper around the dispatch (#62) formats it, so there is no Tent-style fallback handler class.
- Which part (the router or the dispatcher) owns the "no match" exception is the implementer's call. The observable behaviour is what is fixed.

### Testing strategy

- Unit tests for `Route` and `Router` use `new Request([...])`:
  - compilation, including `:id.json`;
  - captured params and decoding;
  - first match wins;
  - duplicate and invalid registrations throw;
  - wrong method gives `null`;
  - trailing slash and case.
- A **routes-table test** loads `config/routes.php` and asserts two things:
  - every endpoint listed in `api/endpoints/` resolves to its handler;
  - no route is shadowed by an earlier one.

### Performance and security

- Matching is a linear scan over about 35 lazily compiled regexes, which is negligible.
- Matching works on the decoded path only. Path traversal can't reach the filesystem, because routes map to classes, not files.
- A decoded `%00` already gets 400 in `Request`.
- Handler and middleware classes are fixed at registration and are never taken from the request.

## Solution

- **Replace the stub** `docs/agents/specs/request-handler/routing.md` with the full spec, which records the decisions above.
  - It follows `docs/agents/specs.md` (title, `Purpose:`, English).
  - It links `request-response/processing-request.md` (`param()`), `request-response/errors.md` (`NotFoundException`), `api/conventions/format.md` (paths) and `rewrite.md` (`%2F`, `/`).
- **Notation:** in `docs/agents/specs/api/endpoints/` (and anywhere else in `docs/agents/specs/` that writes route paths), convert `{id}` / `{uuid}` to `:id` / `:uuid`, so the endpoint specs read the same way as the route files.
- **Consistency:** check the routing rules against `api/conventions/format.md`:
  - root-relative paths ending in `.json`;
  - the single `/v1` exception, `POST /v1/messages`.
- **Index:** keep the `request-handler.md` table row for `routing.md` accurate. Mention the route files if the description changes.
- **Hand-offs**, recorded in `routing.md`:
  - **#60 (handlers):** the handler factory builds the handler class from the `RouteMatch`. It decides where the integer-id helper lives (a method that throws `NotFoundException` when the value is not a positive integer). It also defines the handler base class that `add()` checks with `is_subclass_of`.
  - **#61 (middlewares):** per-route middleware class names come from `add()`. The CORS preflight question applies because routing runs before the hooks: should there be `OPTIONS` routes, or a global hook that runs before routing?
  - **#62 (front controller):** it builds the `Router` from `config/routes.php`, runs the dispatch, and formats the `NotFoundException('Cannot <METHOD> <path>')` raised when nothing matches.

Out of scope: PHP code, tests and the route files themselves, which belong to later implementation issues. Endpoint behaviour is also out of scope.

## Benefits

- One documented, testable routing contract with no global state, before any `Pidgey\Http\` code exists.
- It removes the Tent param-name bug and path re-parsing in handlers.
- Every unknown path and every wrong method gets one predictable 404.
- The endpoint specs and the route files use the same path notation.
