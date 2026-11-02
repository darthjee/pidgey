# Frontend specs

Move the frontend specs from `/admin/` to `/`.

- `frontend/build.md`: Vite `base: '/'`; `make build-fe-release` writes `frontend/dist/` into `source/static/` (git-ignored); production uploads `dist/` into the release's `static/`; replace "Admin serving" with "Serving" pointing at `request-handler/static-files.md`; `public/admin/.htaccess` and `DirectoryIndex` serving retired (the handler sends `no-cache`); `/admin/` dropped with no redirect (old bookmarks get `302 /#/admin/`). Keep the "option C" production-delivery decision, updated to `static/`.
- `frontend/development.md`: "URL" — dev UI at `http://localhost:8030/` (base `/` in dev and builds); "First run" step 4 URL; "Two UIs in development" — `:8030/` (live Vite) and `:8080/` (last release build served by the PHP handler, stale, JSON 404 on a fresh clone); add the decision to keep Vite's own proxy with the three dev/production differences and the rejected alternatives (`FRONTEND_DEV_MODE` PHP proxy, Apache `mod_proxy`), as written in issue #121 "Development mode".
- `frontend.md`: index row for `build.md` ("Build and release: `/`, production delivery").
- `frontend/screens.md`: CSP pending decision re-pointed from `/admin/` to `index.html` at `/`; note `frame-ancestors 'none'` is already decided in `request-handler/static-files.md`.

## Files to Change

- `docs/agents/specs/frontend/build.md`
- `docs/agents/specs/frontend/development.md`
- `docs/agents/specs/frontend.md`
- `docs/agents/specs/frontend/screens.md`
