# Rewrite frontend/build.md as build and release

Keep the file name (links stay valid); retitle to "Frontend Build and Release". Content:

- `yarn build` (Vite) produces `frontend/dist/`; Vite `base: '/admin/'` (dev and build alike).
- **Release:** `make build-fe-release` builds and replaces `source/public/admin` with
  `frontend/dist/.` — link [docker/makefile.md](../docker/makefile.md). Served at `/admin/` by
  `app` on :8080 through the existing `./source` mount (no `dist` volume).
- Drop the old "`dist/` mounted into the web root as `static/`" text.
- `:8080/admin/` shows the last release build and can be stale (see [development.md](development.md)).
- **Interim rule:** until the production decision below is made, `source/public/admin/` is
  git-ignored, so `make build-fe-release` leaves no untracked files.
- **Pending decision — how the built frontend reaches production** (replaces the current
  "bake vs. upload" item; this is the single place it is recorded):
  - **A. Committed** — `source/public/admin` committed; the prod image (built from `source/`)
    picks it up; no upload job. Simplest; build output in git (noisy diffs, stale-bundle risk).
  - **B. Git-ignored, built in the prod image** — multi-stage prod Dockerfile (Node stage runs
    `yarn build`, copies `dist/` into `admin/`). Clean git, always in sync; prod image needs the
    repo root as build context.
  - **C. Git-ignored, uploaded** — `upload_fe_files` uploads `dist/` (majora pattern). Independent
    frontend deploys; needs a deploy target/credentials, two artifacts to keep in sync.
- **Pending decision — `/admin/` serving:** whether `/admin/` → `index.html` needs anything beyond
  `DirectoryIndex`. Constraint either way: the front-controller rewrite skips existing files and
  directories, so `mod_dir` redirects `/admin` → `/admin/` and `/admin/assets/*` never reach PHP.
  Hash routing needs no route fallback.

## Files to Change
- `docs/agents/specs/frontend/build.md` — rewrite as above.
