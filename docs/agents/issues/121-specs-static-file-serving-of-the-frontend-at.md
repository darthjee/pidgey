# Issue: Specs: static-file serving of the frontend at `/`

## Description
Part of epic #120. Today the built admin UI is served by Apache at `/admin/` from `source/public/admin/`, and `/` answers the router's JSON 404. The decision is to serve the single-page application at `/` through a PHP static-file handler (the Oak/Tent pattern), with the build outside the web root. Per AGENTS.md, specs change before code; this issue updates the specs only.

## Problem
The specs describe the admin UI as served by Apache at `/admin/` from `source/public/admin/`, with only `*.json` routed to PHP. Epic #120 decided to serve the single-page application at `/` through a PHP static-file handler from `source/static/`, with a hash-route redirect for deep links. Code must not deviate from the specs (AGENTS.md), so the Router, handler and build/deploy sub-issues (#122, #123, #124) are blocked until the specs describe the new model.

## Expected Behavior
- The specs above describe the new serving model.
- `grep -rn "/admin/" docs/agents/specs | grep -v "/admin/users"` returns nothing that refers to the UI.
- No "Pending decision" left on the points decided in #120.

## Solution
### Spec changes
- `specs/frontend/build.md`: build output lives in `source/static/` (git-ignored, outside `public/`, shipped with the release); Vite `base: '/'`, assets under `/assets/`; `/admin/` as the UI's home is dropped (no redirect); `public/admin/.htaccess` and the `DirectoryIndex` serving are retired; `index.html` no-cache is sent by the handler.
- `specs/frontend/development.md`: dev UI at `http://localhost:8030/`; the "two UIs" section becomes `:8030/` (live Vite, its own `/v1` and `*.json` proxy — no `FRONTEND_DEV_MODE` proxy in PHP) and `:8080/` (last `make build-fe-release` output, served by PHP from `source/static/`).
- `specs/frontend.md`: index row for `build.md` ("Build and release: `/`, production delivery").
- `specs/frontend/screens.md`: re-point the existing CSP pending decision from `/admin/` to `index.html` at `/`, noting `frame-ancestors 'none'` is already decided.
- `specs/request-handler/rewrite.md`: paths that bypass PHP no longer include the UI; update the `/admin/` mentions, edge cases and production verification results.
- `specs/request-handler/routing.md`: a **prefix matcher** (for `/assets/*`) and a **GET fallback** registered last that answers `302 Location: /#<raw REQUEST_URI>`, applied only to **extensionless** paths (last segment has no `.`) outside the `/v1/` prefix — `*.json`, `/favicon.ico`, `/robots.txt`, `/index.php`, `/index.html` and `/v1/*` keep the JSON 404; non-GET unmatched requests keep the JSON 404; `HEAD` still does not fall back to `GET`.
- `specs/architecture/backend.md#entry-point`: routing rule — `*.json` is the API, `POST /v1/messages` the only non-`.json` API route, other GETs are the frontend.
- `specs/architecture/repository-layout.md`: `source/public/` row (also drop the stale "stub `index.php`" wording); new `source/static/` row.
- `specs/api/conventions/format.md`: `*.json` always reaches the API anywhere in the tree; other GET paths belong to the frontend (no longer "under `/admin/`").
- `specs/overview/components.md`: the diagram and the Apache/PHP and React frontend rows (UI served at `/` by the PHP static-file handler).
- `specs/docker/makefile.md` (`FE_RELEASE_DIR`, dev URL), `specs/docker/compose.md` (`app` service description), `specs/docker/volumes.md` (release folder note).
- `specs/request-handler.md`: index row for the new static-file spec, and the flow diagram.
- New spec `specs/request-handler/static-files.md` for `StaticFileHandler`:
  - maps the path to a file under `source/static/`; `/` serves `index.html`;
  - rejects `..`, `\` and dotfile segments; the resolved real path must stay under the base folder (no symlink escape); missing file or directory → 404;
  - extension allow-list (`html`, `js`, `css`, `svg`, `png`, `ico`, `woff`, `woff2`, …; checked against `dist/` whenever a dependency adds a file type) with explicit `Content-Type`; any other extension → 404; no source maps in production;
  - headers: `Content-Length`, `X-Content-Type-Options: nosniff`; `index.html` → `Cache-Control: no-cache`, `Content-Security-Policy: frame-ancestors 'none'`, `X-Frame-Options: DENY`; `/assets/*` → `Cache-Control: public, max-age=31536000, immutable`;
  - static routes carry no route middlewares and never touch the database or build the `Container`;
  - the edge cases below.

### Edge cases
Each recorded in the spec file that owns it.

`routing.md`:

- Unknown `*.json` → JSON 404 (never a redirect); `GET /v1/*` with no route (e.g.
  `GET /v1/messages`) → JSON 404; unmatched non-GET → JSON 404; `HEAD` does not fall back to
  `GET`; the query string is ignored for matching (`/?x=1` serves `index.html`).
- The GET fallback redirects only **extensionless** paths: a path whose last segment has an
  extension (`/favicon.ico`, `/robots.txt`, `/index.html`, `/index.php`) gets the JSON 404,
  so browsers' automatic requests never receive an HTML page. SPA deep links never carry an
  extension. (This replaces #120's "`/index.php` → 302".)
- The prefix is `/assets/`: `/assets` and `/assetsX` are not matched by it; being
  extensionless, they get the 302 to the SPA. Accepted, no special case.

`static-files.md` — redirect:

- Query string and trailing slash kept (`/foo?x=1` → `302 /#/foo?x=1`, `/foo/` → `/#/foo/`).
- `Location` built from the raw `REQUEST_URI`, never decoded (no header splitting); always
  starts with `/#` (`//evil.com` → `/#//evil.com`, no open redirect).

`static-files.md` — handler:

- A missing asset or a directory under `/assets/` → 404, never a redirect (stale bundle names
  after a release fail cleanly).
- `..`, `\`, dotfile segments, `%2e%2e` (after the `Request` decodes it) and symlinks
  resolving outside `source/static/` → rejected.
- Fresh clone (no `source/static/index.html`) → JSON 404 on `/`; the debug log names the
  resolved path.
- Only `/` and `/assets/*` are served: root-level files in Vite's `public/` folder (a future
  favicon, `robots.txt`) are built but unreachable. Such files go under `/assets/` and are
  linked from `index.html` (`<link rel="icon" href="/assets/…">`), or get an explicit route.
- Dev source paths (`/assets/js/main.jsx`) exist only on the Vite dev server; in production
  they are 404 — the built `index.html` references the hashed bundles.

`rewrite.md`:

- Apache still 404s dotfile segments before PHP runs.

### Development mode
Decision: keep Vite's own dev server and proxy (no change in approach).

- The dev UI moves to `http://localhost:8030/` (`base: '/'` in dev and builds, so dev keeps
  matching the release layout `/` + `/assets/`). Vite serves `index.html`, source modules and
  HMR, and proxies `/v1` and `*.json` to `app:80`. The origin check passes because the browser
  sees one origin (`:8030`, `Sec-Fetch-Site: same-origin`).
- `development.md` states the dev/production differences:
  1. Unknown paths on `:8030` get Vite's SPA fallback (`index.html`, 200) instead of
     `302 /#<path>`; harmless with hash routing — check the real redirect on `:8080`.
  2. Vite does not send the handler's headers (`no-cache`, `immutable`, `nosniff`,
     `frame-ancestors`); test them on `:8080` after `make build-fe-release`.
  3. `:8080/` on a fresh clone (no build) is a JSON 404.
- "Two UIs in development": `http://localhost:8030/` (live Vite, hot reload) and
  `http://localhost:8080/` (last `make build-fe-release` output, served by the PHP static-file
  handler from `source/static/`; can be stale).

Rejected alternatives (recorded in `development.md`):

- **PHP proxies to Vite behind `FRONTEND_DEV_MODE=true`** (Oak's pattern): needs an HTTP
  client in PHP for dev only, and HMR's WebSocket upgrade cannot be proxied by PHP under
  Apache.
- **Apache `mod_proxy` to Vite in the dev image**: the dev Apache config would diverge from
  production, which has no vhost access and no `mod_proxy`.

### Out of scope
- Code changes (Router, handler, build/deploy wiring); conditional GET (ETag/304); a full CSP.
- Non-spec docs that describe the project as built — `docs/agents/architecture.md`, `docs/agents/folder-structure.md`, `.claude/agents/backend.md` and `docs/agents/deployment/*.md` — change with the code in the "Move the frontend build and deploy to `source/static/`" sub-issue (#124).

## Benefits
- One source of truth for the new serving model before any code lands; #122–#124 implement against it.
- A single routing rule (`*.json` → API, extensionless GET → SPA, `/` and `/assets/*` → static files) shared by dev and production.
- Security and edge-case behavior (traversal, header injection, open redirect, allow-list, headers) decided up front.
