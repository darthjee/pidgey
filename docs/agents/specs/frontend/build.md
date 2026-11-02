# Frontend Build and Release

Purpose: define how the frontend is built, released into `source/static/` and served at `/`.

## Build

- `yarn build` (Vite) produces `frontend/dist/`.
- Vite `base: '/'` is used in both development and builds, so asset paths are the same in
  dev and release (see [development.md](development.md#url)). The built `index.html` is at the
  root and the hashed bundles are under `/assets/`.
- The release build emits **no source maps**; the static-file handler does not serve `.map`
  files ([request-handler/static-files.md](../request-handler/static-files.md#extension-allow-list)).

## Release

- `make build-fe-release` builds the frontend and replaces `source/static/` with the contents
  of `frontend/dist/` (target definition: [docker/makefile.md](../docker/makefile.md)).
- `source/static/` is **outside the web root** (`source/public/`), so Apache never serves it
  directly. The release is served at `/` by the PHP static-file handler in `app` (host :8080)
  through the existing `./source` mount; no separate `dist` volume is needed (see
  [docker/volumes.md](../docker/volumes.md)).
- `http://localhost:8080/` shows the **last** release build and can be stale; the live UI is
  the Vite dev server on :8030 (see [development.md](development.md#two-uis-in-development)).
  On a fresh clone there is no build, so `/` answers a JSON 404.
- In production the UI is served from the release's `static/` folder at `/`, same origin as the
  API (see [Production delivery](#production-delivery)).

`source/static/` is git-ignored, so `make build-fe-release` leaves no untracked files and build
output never lives in git.

## Production delivery

**Decision — option C, git-ignored and uploaded.** The deploy workflow builds the frontend
(`vite build`, in the `build_frontend` job, without the deploy context) and uploads `dist/`
into the staging release's `static/` folder, next to `public/`, in the same release as the
backend (the `upload_frontend` job, see
[deployment/pipeline.md](../../deployment/pipeline.md#deploy-chain)). Both halves come from the
same tag and are swapped in together, so they stay in sync.

Rejected options:

- **A. Committed** (`source/static` in git, shipped with the backend): build output in git
  means noisy diffs and a risk of shipping a stale bundle that does not match the tag's source.
- **B. Built in a production image** (multi-stage Dockerfile with a Node stage): production
  does not run Docker images (it is an SSH/rsync host), so there is no production image to
  build it into (see [docker/images.md](../docker/images.md)).

## Serving

**Decision:** the UI is served at `/` by the PHP static-file handler (`StaticFileHandler`) from
`source/static/`. The handler, its routes, the extension allow-list, the response headers and
the hash-route redirect are specified in
[request-handler/static-files.md](../request-handler/static-files.md).

- `GET /` serves `index.html` and `GET /assets/*` the hashed bundles. Any other extensionless
  `GET` outside `/v1/` gets `302 Location: /#<path>`, so a deep link lands on the hash route
  ([routing.md](../request-handler/routing.md#get-fallback)). Hash routing
  ([screens.md](screens.md#routing)) needs nothing more from the server.
- The handler sends `Cache-Control: no-cache` for `index.html`, so browsers revalidate it after
  a release and pick up the new hashed asset names, and
  `Cache-Control: public, max-age=31536000, immutable` for `/assets/*`.
- **Retired:** the web-root `admin` folder, Apache's `DirectoryIndex` serving of the UI, the
  `mod_dir` redirect and the nested `.htaccess` that sent `no-cache` for `index.html`. The
  Vite project's static `public/` folder no longer carries an `.htaccess`.
- **The old UI path is dropped, with no redirect of its own.** Being extensionless, an old
  bookmark gets the GET fallback like any deep link (a `302` to the same path behind `/#`),
  and the single-page application falls back to home for an unknown hash route
  ([screens.md](screens.md#routing)).
- Root-level files in Vite's `public/` folder are built but not served; such files go under
  `/assets/` ([request-handler/static-files.md](../request-handler/static-files.md#edge-cases)).

## Pending decisions

None.
