# New static-files spec

Create `docs/agents/specs/request-handler/static-files.md` describing `StaticFileHandler` and the frontend routes, and index it.

Contents (from issue #121, "Spec changes" and "Edge cases"):

- Purpose and place in the flow (a `RequestHandler` like any other; routes in `source/config/routes/frontend.php`).
- Base folder `source/static/` (outside `public/`, git-ignored, shipped with the release) and why it must be outside the web root (the `.htaccess` lets Apache serve existing files under `public/`).
- Routes, in order: `GET /` → `index.html`; `GET /assets/*` (prefix); API routes; extensionless-GET fallback → `302 Location: /#<raw REQUEST_URI>`.
- Path mapping and rejection: `..`, `\`, dotfile segments, `%2e%2e` after decoding, symlinks resolving outside the base folder; missing file or directory → 404 (debug log names the resolved path).
- Extension allow-list with explicit `Content-Type` (`html`, `js`, `css`, `svg`, `png`, `ico`, `woff`, `woff2`, …); other extensions → 404; re-check against `dist/` when a dependency adds a file type; no source maps in production.
- Headers: `Content-Length`, `X-Content-Type-Options: nosniff`; `index.html` → `Cache-Control: no-cache`, `Content-Security-Policy: frame-ancestors 'none'`, `X-Frame-Options: DENY`; `/assets/*` → `Cache-Control: public, max-age=31536000, immutable`.
- Redirect rules: query and trailing slash kept; raw `REQUEST_URI`, never decoded (no header splitting); always `/#…` (no open redirect).
- Performance: static routes carry no route middlewares, never touch the database or build the `Container`; whole-file read, no streaming; PHP-per-asset trade-off accepted.
- Edge cases: fresh clone → JSON 404 on `/`; root-level files in Vite's `public/` are unreachable (put them under `/assets/` or add a route); dev source paths 404 in production.
- Testing expectations (fixture folder) and Pending decisions (conditional GET / ETag, full CSP — follow-ups).
- Rejected: Apache-only serving from the web root; serving `index.html` for every unknown path (history-router fallback); Oak's `FRONTEND_DEV_MODE` PHP proxy.

## Files to Change

- `docs/agents/specs/request-handler/static-files.md` — new file.
- `docs/agents/specs/request-handler.md` — index row for `static-files.md`; flow diagram mentions static files and the GET fallback.
