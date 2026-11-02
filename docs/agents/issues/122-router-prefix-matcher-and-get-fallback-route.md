# Issue: Router: prefix matcher and GET fallback route

## Description
Part of epic #120. Add two capabilities to `Pidgey\Http\Router` so the single-page application can be served at `/`: **prefix routes** (`GET /assets/*`) and a **GET fallback** for SPA deep links. The behaviour is fixed by [request-handler/routing.md](../specs/request-handler/routing.md#prefix-routes) (merged in #121); the registration API is the implementer's call.

## Problem
Today `Route` compiles only exact literals and `:param` segments (`[^/]+`, never crossing `/`), and `Router::match()` returns `null` for anything unmatched, which the `FrontController` turns into the JSON 404 `Cannot <METHOD> <path>`. Neither nested asset paths (`/assets/fonts/x.woff2`) nor deep links (`/users/42`) can be routed.

## Expected Behavior
- **Prefix route** `GET /assets/*`: matches `/assets/` followed by anything, including further `/`. `/assets` and `/assetsX` are not matched. Literal, case-sensitive, matched on the decoded `path()`. The router does not check the rest of the path; the handler reads it from `path()`.
- A prefix route follows the normal method rule and first-match-wins order.
- **GET fallback**: tried after every other route, whatever its registration position. It matches only when the method is `GET`, no other route matched, the last segment contains no `.`, and the path is outside the `/v1/` prefix.
- Everything else keeps the JSON 404: unknown `*.json`, `GET /v1/*` with no route, `/favicon.ico`, `/robots.txt`, `/index.html`, `/index.php`, unmatched non-`GET`, and `HEAD`.
- Existing exact and `:param` routes, duplicate detection and the 404 message are unchanged.

## Solution
- **Prefix routes** use a trailing `/*` in the ordinary pattern: `$router->get('/assets/*', Handler::class)`. `/*` is allowed only at the very end of a pattern; anywhere else it throws `LogicException` at registration. It compiles to an anchored prefix match (`/assets/` followed by anything), and no param is captured for the rest.
- Duplicate detection covers prefix patterns: registering `GET /assets/*` twice throws `LogicException`.
- **GET fallback**: `$router->fallback(Handler::class)`.
  - GET only, with no route middlewares; the global middlewares still run.
  - Registering a second fallback, or a non-`RequestHandler` class, throws `LogicException`.
  - It is stored apart from the ordinary routes, so `match()` always tries it last, whatever its registration position.
  - The returned `RouteMatch` carries the fallback handler class and no params.
- The fallback rule (GET, extensionless last segment, outside the `/v1/` prefix) lives in the router and is not configurable. `/v1` (no trailing slash) is extensionless and outside `/v1/`, so it gets the fallback; `/v1/` and `/v1/*` get the 404. This is accepted.
- No frontend route is registered here: `routes/frontend.php`, `StaticFileHandler` and the redirect handler belong to the next sub-issue.

### Tests
- `RouteTest` / `RouterTest`:
  - `/assets/` matches nested paths;
  - `/assets` and `/assetsX` do not match;
  - a wrong method gives `null`;
  - duplicate prefix registration throws.
- Fallback:
  - extensionless `GET` (`/foo`, `/foo/`, `/users/42`) → fallback;
  - `/foo.bar/baz` (a dot only in an earlier segment) → fallback;
  - `*.json`, `/v1/messages`, `/favicon.ico`, `/index.php`, `POST /foo` and `HEAD /foo` → `null`;
  - an ordinary route registered after the fallback still wins;
  - `/v1` → fallback, `/v1/` → `null`;
  - a second `fallback()` call and a mid-pattern `*` throw `LogicException`.
- `FrontControllerTest`: the existing 404 behaviour is unchanged.

### Out of scope
`StaticFileHandler`, `routes/frontend.php`, the redirect handler, and the routes-table assertions about the frontend routes (all in the next sub-issue).

## Benefits
Unblocks serving the frontend at `/` from PHP while keeping every API miss a JSON 404.
