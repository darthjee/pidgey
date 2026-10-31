# Frontend Build and Release

Purpose: define how the frontend is built, released into the web root and served at `/admin/`.

## Build

- `yarn build` (Vite) produces `frontend/dist/`.
- Vite `base: '/admin/'` is used in both development and builds, so asset paths are the same in
  dev and release (see [development.md](development.md)).

## Release

- `make build-fe-release` builds the frontend and replaces `source/public/admin` with the
  contents of `frontend/dist/` (target definition: [docker/makefile.md](../docker/makefile.md)).
- The release is served at `/admin/` by Apache in `app` (host :8080) through the existing
  `./source` mount; no separate `dist` volume is needed (see
  [docker/volumes.md](../docker/volumes.md)).
- `http://localhost:8080/admin/` shows the **last** release build and can be stale; the live UI
  is the Vite dev server on :8030 (see [development.md](development.md#two-uis-in-development)).
  On a fresh clone there is no build, so `/admin/` falls through to `index.php` (JSON 404).
- In production the UI is served from the release's `public/admin/` folder at `/admin/`, same
  origin as the API (see [Production delivery](#production-delivery)).

`source/public/admin/` is git-ignored, so `make build-fe-release` leaves no untracked files and
build output never lives in git.

## Production delivery

**Decision — option C, git-ignored and uploaded.** The deploy workflow builds the frontend
(`vite build`) and uploads `dist/` into the staging release's `public/admin/`, in the same
release as the backend (the `upload_frontend` job, see
[deployment/pipeline.md](../deployment/pipeline.md#deploy-jobs) and
[deployment/release.md](../deployment/release.md#frontend-upload-upload_frontend)). Both halves
come from the same tag and are swapped in together, so they stay in sync.

Rejected options:

- **A. Committed** (`source/public/admin` in git, shipped with the backend): build output in
  git means noisy diffs and a risk of shipping a stale bundle that does not match the tag's
  source.
- **B. Built in a production image** (multi-stage Dockerfile with a Node stage): production
  does not run Docker images (it is an SSH/rsync host), so there is no production image to
  build it into (see [docker/images.md](../docker/images.md)).

## Admin serving

**Decision:** Apache's `DirectoryIndex` is enough to serve `/admin/` → `index.html`; no rewrite
is needed. The front-controller rewrite skips existing files and directories (see
[architecture/backend.md](../architecture/backend.md)), so `mod_dir` redirects `/admin` →
`/admin/` and `/admin/assets/*` never reach PHP. The bypass rules live in
[request-handler/rewrite.md](../request-handler/rewrite.md). Hash routing
([screens.md](screens.md)) needs no route fallback.

`public/admin/.htaccess` exists only to send `Cache-Control: no-cache` for `index.html`, so
browsers revalidate it after a release and pick up the new hashed asset names (see
[deployment/edge-cases.md](../deployment/edge-cases.md#frontend)). Hashed Vite assets keep
their default caching. The file is part of the frontend build output (kept in the Vite
project's static `public/` folder, which Vite copies into `dist/`), so it reaches both
`make build-fe-release` and the production upload while `source/public/admin/` stays
git-ignored.

## Pending decisions

None.
