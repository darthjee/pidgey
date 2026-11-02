# Issue: StaticFileHandler and frontend routes (`/`, `/assets/*`, hash redirect)

## Description
Part of epic #120. Serve the built single-page application at `/` through a PHP static-file handler (the Oak/Tent pattern). The files come from `source/static/`, which is outside the web root, so Apache never serves them directly. The behaviour is fixed by [static-files.md](../specs/request-handler/static-files.md) and [routing.md](../specs/request-handler/routing.md) (merged in #121).

Depends on #122 (router prefix matcher and GET fallback).

## Problem
- Nothing answers `GET /` today: the only route is `GET /health.json`, so the admin UI cannot live at `/`.
- #122 gives the router prefix routes (`/assets/*`) and `Router::fallback()`, but there is still no handler to serve files, no redirect handler and no `routes/frontend.php`.

## Expected Behavior
- `GET /` serves `source/static/index.html` with `Cache-Control: no-cache`, `Content-Security-Policy: frame-ancestors 'none'` and `X-Frame-Options: DENY`.
- `GET /assets/<rest>` serves `source/static/assets/<rest>` with `Cache-Control: public, max-age=31536000, immutable`.
- Every file response has `Content-Type` from the allow-list, `Content-Length` and `X-Content-Type-Options: nosniff`.
- An extensionless GET outside `/v1/` with no other match gets `302 Location: /#<raw REQUEST_URI>` with an empty body.
- Anything rejected, missing or disallowed gets the JSON 404 `Cannot GET <path>`.

## Solution
### `StaticFileHandler` (`source/lib/Http/Handler/StaticFileHandler.php`)
- A single handler serves both routes. `/` maps to `index.html` and gets the index headers; `/assets/<rest>` maps to `assets/<rest>` and gets the immutable cache header.
- It is constructed as `__construct(?string $baseDir = null, ?callable $logger = null)`:
  - the defaults are `source/static/` (resolved from the source tree, never from the request) and `error_log()`, the same pattern `ErrorFormatter` uses;
  - the defaults are needed because `FrontController` builds handlers with `new $class()`;
  - tests pass a fixture folder and a recording logger.
- Rejections (404):
  - a segment that is `..`;
  - a `\`;
  - a dotfile segment;
  - a resolved real path outside the real base folder (symlink escape);
  - a missing file or a directory;
  - an extension that is not on the allow-list (`html js css svg png ico woff woff2`, matched case-insensitively).
- Every 404 throws `NotFoundException('Cannot GET <path>')` and logs one line naming the resolved path. The path is never sent to the client.
- The file is read whole into the body. There is no streaming, no range support and no `Container` or database access.

### `HashRedirectHandler` (`source/lib/Http/Handler/HashRedirectHandler.php`)
- Answers `302` with `Location: /#<raw REQUEST_URI>` and an empty body.
- Needs a raw-URI accessor: add `Request::uri()`, which returns the raw `REQUEST_URI` (or `/` when it is missing), plus a matching `ProcessingRequest::uri()`.
- The URI is never decoded, so the query string and the trailing slash are kept, and `//evil.com` becomes `/#//evil.com`.

### Routes (`source/config/routes/frontend.php`)
- `routes.php` requires `frontend.php` first, then the API route files.
- `frontend.php` registers `get('/', StaticFileHandler)` and `get('/assets/*', StaticFileHandler)`.
- `routes.php` registers `$router->fallback(HashRedirectHandler::class)` after every API file.
- No route middlewares are attached.

### Tests
- `StaticFileHandlerTest` runs on a fixture folder (`source/tests/fixtures/static/`):
  - `/` is served with its headers;
  - an asset is served with its type, length and cache header;
  - the `woff` and `woff2` fonts are served;
  - each of these gives a 404 with a log line: a missing file, a directory, `..`, `\`, a dotfile, a symlink escape (the symlink is created in the test setup) and an unknown extension;
  - a missing `index.html` (fresh clone) gives a 404 on `/`.
- `HashRedirectHandlerTest` covers `/foo?x=1` → `/#/foo?x=1`, `/foo/` → `/#/foo/`, a raw `%0D%0A` that stays encoded, and `//evil.com` → `/#//evil.com`.
- `RequestTest` and `ProcessingRequestTest` cover `uri()`.
- `RoutesTest` (moved here from #122):
  - the frontend routes come first and the fallback is set;
  - `GET /` and `GET /assets/x.js` match `StaticFileHandler`;
  - `GET /foo` matches the fallback;
  - `/nope.json`, `/favicon.ico`, `/index.php`, `GET /v1/messages` and `HEAD /` do not match;
  - `testUnknownPathsDoNotMatch` is updated, since `GET /` now matches.
- Keep 100% coverage. Ask the `security` agent for a review before the PR is marked ready.

### Out of scope
- Moving the build/deploy output to `source/static/` (a separate sub-issue, merged after this one).
- Conditional GET (`ETag`/`304`).
- A full CSP.

## Benefits
- The admin UI is reachable at `/` on the same origin as the API, with no Apache-specific rules.
- Deep links keep working through hash routing, while unknown API paths and browser probes keep the JSON 404.
- Serving is locked down: there is an allow-list, traversal is rejected, and the headers are explicit.
