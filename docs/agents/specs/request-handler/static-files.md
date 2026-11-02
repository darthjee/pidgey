# Static Files

Purpose: define how the built frontend is served at `/` by the PHP static-file handler (`StaticFileHandler`): the frontend routes, path mapping and rejection, the extension allow-list, the response headers and the hash-route redirect.

Part of the request flow indexed in [request-handler.md](../request-handler.md). The handler is a [`RequestHandler`](handlers.md#requesthandler) like any other: Apache hands the request to the front controller ([rewrite.md](rewrite.md)), the router matches a frontend route ([routing.md](routing.md)), and the handler returns a [`Response`](request-response/response.md). How the frontend is built and released is in [frontend/build.md](../frontend/build.md).

## Base folder

- The handler serves files from **`source/static/`**, the frontend build output ([frontend/build.md](../frontend/build.md#release)).
- `source/static/` is **outside the web root** (`source/public/`), **git-ignored**, and **shipped with the release** next to `public/` ([architecture/repository-layout.md](../architecture/repository-layout.md)).
- **Why outside `public/`:** the `.htaccess` lets Apache serve every existing file under `public/` before PHP runs ([rewrite.md](rewrite.md#paths-that-bypass-php)). A build inside the web root would be served by Apache, with Apache's headers, and the handler's rules below (allow-list, headers, rejections) would not apply.
- The base folder is fixed by the source tree. It is never taken from the request.

## Routes

The frontend routes live in `source/config/routes/frontend.php` ([routing.md](routing.md#route-files)). The overall order in `source/config/routes.php` is:

1. **`GET /`** → `StaticFileHandler`, serving `index.html`.
2. **`GET /assets/*`** → `StaticFileHandler`, a **prefix** route ([routing.md](routing.md#prefix-routes)) serving the file under `source/static/assets/`.
3. **The API routes** (operational, auth, admin, organization, v1).
4. **The GET fallback**, registered last: `302 Location: /#<raw REQUEST_URI>` for extensionless GET paths outside `/v1/` ([routing.md](routing.md#get-fallback), [Redirect](#redirect)).

- Only `/` and `/assets/*` serve files. No other path reaches the filesystem.
- The query string is ignored for matching: `/?x=1` serves `index.html` ([routing.md](routing.md#matching-and-order)).
- `HEAD` does not fall back to `GET`, so `HEAD /` and `HEAD /assets/*` get the JSON 404 ([routing.md](routing.md#no-match)).

## Path mapping

- `/` maps to `source/static/index.html`.
- `/assets/<rest>` maps to `source/static/assets/<rest>`. The path is the decoded [`Request::path()`](request-response/request.md#method-path-and-query).
- The path is **rejected** (404) when any of these holds:
  - a segment is `..` (including `%2e%2e`, which the `Request` has already decoded to `..`);
  - it contains `\`;
  - a segment starts with `.` (dotfiles). Apache already 404s these before PHP runs ([rewrite.md](rewrite.md#security)); the handler checks again so it does not depend on the `.htaccess`.
- The **resolved real path** (symlinks followed) must stay under the real path of the base folder. A symlink that resolves outside `source/static/` is rejected.
- A **missing file** or a **directory** is a 404. It is never a redirect, so a stale bundle name after a release fails cleanly.
- Every 404 from the handler is the **JSON 404** (`NotFoundException`, [request-response/errors.md](request-response/errors.md#exceptions)) with the router's message, `Cannot GET <path>` ([routing.md](routing.md#no-match)). A rejected, missing or disallowed file looks the same as an unknown path.
- The handler writes a **debug log** line naming the resolved path on a 404, so a missing build (fresh clone) is easy to diagnose. The path is never sent to the client.

## Extension allow-list

Only files whose extension is on the allow-list are served, each with an explicit `Content-Type`. The type is never sniffed from the file content.

| Extension | `Content-Type` |
| --- | --- |
| `html` | `text/html; charset=utf-8` |
| `js` | `text/javascript; charset=utf-8` |
| `css` | `text/css; charset=utf-8` |
| `svg` | `image/svg+xml` |
| `png` | `image/png` |
| `ico` | `image/x-icon` |
| `woff` | `font/woff` |
| `woff2` | `font/woff2` |

- Any other extension, or no extension, is a 404. Matching ignores case.
- The list covers what the current build emits (the bundles, Bootstrap's CSS and the Bootstrap Icons fonts). When a dependency adds a file type, the list is **re-checked against `frontend/dist/`** and extended in this spec first.
- **No source maps in production:** `map` is not on the list, and the release build does not emit them ([frontend/build.md](../frontend/build.md#build)).

## Response headers

Every file response (`200`):

- `Content-Type` from the [allow-list](#extension-allow-list);
- `Content-Length`, the file size;
- `X-Content-Type-Options: nosniff`.

`index.html` (`GET /`) also gets:

- `Cache-Control: no-cache`, so browsers revalidate it after a release and pick up the new hashed asset names;
- `Content-Security-Policy: frame-ancestors 'none'`;
- `X-Frame-Options: DENY`.

`/assets/*` also gets:

- `Cache-Control: public, max-age=31536000, immutable`. Vite puts a content hash in every bundle name, so a file under `/assets/` never changes.

The CORS step still adds its headers to the final response ([front-controller.md](front-controller.md#cors-step-pidgeyhttpcors)); a same-origin page load has nothing to add.

## Redirect

The GET fallback ([routing.md](routing.md#get-fallback)) turns a deep link into a hash route the single-page application understands ([frontend/screens.md](../frontend/screens.md)).

- **Answer:** `302` with `Location: /#<raw REQUEST_URI>` and an empty body.
- **Query string and trailing slash are kept:** `/foo?x=1` → `/#/foo?x=1`, `/foo/` → `/#/foo/`.
- **Raw, never decoded:** `Location` is built from `$_SERVER['REQUEST_URI']` as received, not from the decoded `path()`. A `%0D%0A` stays encoded, so it cannot split the header; `Response::addHeader()` rejects CR and LF anyway ([request-response/response.md](request-response/response.md#response)).
- **No open redirect:** `Location` always starts with `/#`. `//evil.com` → `/#//evil.com`, which stays on Pidgey's origin.
- Which paths fall back, and which keep the JSON 404, is owned by [routing.md](routing.md#get-fallback).

## Performance

- Static routes carry **no route middlewares**. The global `OriginCheck` runs, and always lets `GET` through ([api/conventions/origin-check.md](../api/conventions/origin-check.md)).
- They **never touch the database**. The handler uses no `Container` service beyond the logger built at boot ([handlers.md](handlers.md#laziness-and-failure)), so serving a file opens no connection.
- The file is **read whole** into the response body. There is no streaming and no range support: the bundles are small.
- Every asset request runs PHP. This costs more than Apache serving the file, but it is accepted: the browser caches `/assets/*` as `immutable`, so each bundle is fetched once per release.

## Edge cases

- **Fresh clone** (no `source/static/index.html`): `GET /` gets the JSON 404, and the debug log names the resolved path.
- **Missing asset or directory under `/assets/`:** JSON 404, never a redirect.
- **Traversal:** `..`, `\`, dotfile segments, `%2e%2e` and symlinks resolving outside `source/static/` are rejected with a 404.
- **Root-level files in Vite's `public/` folder** (a future favicon or `robots.txt`) are built into `source/static/` but are **unreachable**: only `/` and `/assets/*` are served. Such files go under `/assets/` and are linked from `index.html` (`<link rel="icon" href="/assets/…">`), or get an explicit route added in this spec.
- **Dev source paths** (`/assets/js/main.jsx`) exist only on the Vite dev server ([frontend/development.md](../frontend/development.md)). In production they are 404: the built `index.html` references the hashed bundles.
- **Redirect cases** are listed under [Redirect](#redirect).

## Testing

- **Unit tests** build the handler on a **fixture folder** (not `source/static/`) and cover:
  - `/` serves `index.html` with its headers;
  - an asset is served with its `Content-Type`, `Content-Length` and the `immutable` cache header;
  - a missing file, a directory, a disallowed extension, `..`, `\`, a dotfile segment and a symlink pointing outside the fixture folder each give a 404.
- **Redirect tests** cover the kept query string and trailing slash, the raw `REQUEST_URI` and `//evil.com`.
- The routes-table test ([routing.md](routing.md#testing)) asserts that the frontend routes come first and the GET fallback last.

## Rejected

- **Apache-only serving from the web root** (the build in a web-root subfolder, `DirectoryIndex`, a nested `.htaccess` for `no-cache`): the UI could not live at `/` without Apache shadowing the API's routing rules, and the headers would depend on per-folder `.htaccess` files.
- **Serving `index.html` for every unknown path** (a history-router fallback): unknown API paths and browsers' automatic requests (`/favicon.ico`) would get an HTML `200`. The single-page application uses hash routing, so a `302` to `/#<path>` is enough.
- **Oak's `FRONTEND_DEV_MODE` PHP proxy to Vite:** see [frontend/development.md](../frontend/development.md#rejected-alternatives).

## Pending decisions

- **Conditional GET (`ETag` / `304`):** not in v1. `index.html` is small and `no-cache` revalidates it with a full `200`; a follow-up may add `ETag`.
- **A full Content-Security-Policy** for `index.html`: only `frame-ancestors 'none'` is decided. The rest stays open in [frontend/screens.md](../frontend/screens.md).
