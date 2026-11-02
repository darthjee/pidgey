# Issue: Move the frontend build and deploy to `source/static/`

## Description
Part of epic #120. Once `StaticFileHandler` (#123) serves the single-page application at `/` from `source/static/`, the frontend build, the dev server and the deploy must target that folder instead of `source/public/admin/`. `/admin/` stops being the UI's home. The specs already describe the target state (#121); this issue changes the code, config and the non-spec docs to match.

## Problem
- `frontend/vite.config.js` builds with `base: '/admin/'`, so the built `index.html` links `/admin/assets/...`, which no route serves.
- `make build-fe-release` (`FE_RELEASE_DIR=source/public/admin`) and the CircleCI `upload_frontend` job (`DEPLOY_PATH=public/admin/`) put the build inside the web root, where Apache serves it directly and the handler's rules do not apply.
- `frontend/public/.htaccess` only exists to send `no-cache` for `index.html` under `public/admin/`. The handler now does that.
- The README, the deployment docs, `architecture.md`, `folder-structure.md` and `.claude/agents/backend.md` still describe the UI at `/admin/`.

## Expected Behavior
- `make build-fe-release` fills `source/static/`, and `http://localhost:8080/` shows the UI.
- The Vite dev UI is at `http://localhost:8030/`. The `/v1` and `*.json` proxy is unchanged.
- A tagged deploy uploads the build into the release's `static/` folder, next to `public/`, and the post-release checks pass.
- No doc outside `docs/agents/issues/` and `docs/agents/plans/` still describes the UI at `/admin/` or under `public/admin/`.

## Solution
**Depends on #123** (`StaticFileHandler` and `routes/frontend.php`). This must merge after it, or production loses the UI.

### Frontend (`frontend` agent)
- `frontend/vite.config.js`: `base: '/admin/'` → `'/'`. `build.sourcemap` stays off (the default).
- Delete `frontend/public/.htaccess`. The handler sends `Cache-Control: no-cache` for `index.html`.
- The client-side hash routes (`#/admin/users`, ...) are unrelated to the base path and do not change.

### Build and deploy (`infra` agent)
- `Makefile`: `FE_RELEASE_DIR?=source/static`.
- `.circleci/config.yml` `upload_frontend`: `DEPLOY_PATH=public/admin/` → `static/`. `.circleci/backend-exclude.txt` does not list `static/` and stays as is.
- `.gitignore`: add `source/static/` and drop `source/public/admin/`. A stale `source/public/admin/` left in a dev clone then shows up as untracked in `git status`, which flags it for deletion. No Makefile cleanup is added.
- `docs/agents/deployment/`: replace every `public/admin/` and `/admin/` reference with `static/` and `/`:
  - `server.md`: the live-release row, the web-directory section (the UI is served by the PHP handler from `static/`, outside the web root, and the `public/admin/.htaccess` bullet is removed), and the HSTS note (static files now get HSTS because they go through `public/index.php`, not through an inherited `.htaccess`).
  - `pipeline.md`: the `upload_frontend` job row, the deploy-chain prose, the `deploy_frontend.sh upload` row and the release-content paragraph (`static/` added next to `public/`).
  - `operations.md` post-release checks: `/admin/` loads → `/` loads, and the HSTS `curl` targets `/`. Add two checks: `GET /does-not-exist` → `302 Location: /#/does-not-exist`, and `curl -sI -H 'Accept-Encoding: gzip' https://<domain>/assets/<bundle>.js` shows `Content-Encoding: gzip`.
  - If the host turns out not to gzip PHP output, record it in `known-gaps.md`. Fixing it is out of scope.

### Non-spec docs (`architect`)
These describe the project as built; #121 updated only the specs.
- `README.md`: the dev UI URL `:8030/admin/` → `:8030/`.
- `docs/agents/architecture.md`: the Frontend bullet and the `public/` section.
- `docs/agents/folder-structure.md`: the `frontend/` row, the `public/` row (also stale about the placeholder stub), and a new `static/` row.
- `.claude/agents/backend.md`: the `public/` bullet, plus `static/` (the built frontend, owned by `frontend`/`infra`, served by `StaticFileHandler`).

### Backward compatibility
Old `/admin/` bookmarks get `302 /#/admin/` and the SPA's not-found screen. This is accepted: there are no real users. The release swap removes the old `public/admin/`, with no manual cleanup on the host.

### Out of scope
- Spec changes (done in #121).
- `StaticFileHandler`, the routes and the redirect (#123).
- Enabling gzip on the host.

## Benefits
- The UI lives at `/` on the same origin as the API, served with the handler's allow-list and headers instead of Apache defaults.
- Build output stays out of the web root and out of git.
- Dev (`:8030/`) and release (`:8080/`) use the same paths.
