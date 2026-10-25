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
- In production the UI is served from the web root's `admin/` folder, same origin as the API.

**Interim rule:** until the production decision below is made, `source/public/admin/` is
git-ignored, so `make build-fe-release` leaves no untracked files.

## Pending decisions

**Pending decision — how the built frontend reaches production.** This is the single place this
decision is recorded; [docker/images.md](../docker/images.md) and
[deployment/pipeline.md](../deployment/pipeline.md) link here. Options:

- **A. Committed** — `source/public/admin` is committed; the production image (built from
  `source/`) picks it up; no upload job. Simplest, but build output lives in git (noisy diffs,
  stale-bundle risk).
- **B. Git-ignored, built in the production image** — multi-stage production Dockerfile (a Node
  stage runs `yarn build` and copies `dist/` into `admin/`). Clean git, always in sync; the
  production image needs the repository root as build context.
- **C. Git-ignored, uploaded** — the `upload_fe_files` job uploads `dist/` (majora pattern).
  Independent frontend deploys; needs a deploy target and credentials, and two artifacts to keep
  in sync.

**Pending decision — `/admin/` serving:** whether `/admin/` → `index.html` needs anything beyond
Apache's `DirectoryIndex` (e.g. an `.htaccess` rewrite). Constraint either way: the
front-controller rewrite skips existing files and directories (see
[architecture/backend.md](../architecture/backend.md)), so `mod_dir` redirects `/admin` →
`/admin/` and `/admin/assets/*` never reach PHP. Hash routing ([screens.md](screens.md)) needs no
route fallback.
