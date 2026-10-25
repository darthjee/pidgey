# Add frontend/development.md and update the index

Create `docs/agents/specs/frontend/development.md` (title + `Purpose:` line) describing the dev
workflow, linking to `docker/*.md` for definitions instead of copying YAML/Makefile snippets:

- **Images:** `vite_pidgey-base` (Node via `darthjee/node`, yarn cache pre-warmed with
  `darthjee/scripts` `yarn_builder.sh`, repo-root build context) with `vite_pidgey` on top — link
  [docker/images.md](../docker/images.md).
- **Service:** `frontend_dev` runs `yarn server`, mounts `./frontend` and
  `./docker_volumes/node_modules`, depends on `app` — link [docker/compose.md](../docker/compose.md).
- **API access:** Vite proxies `/v1` to `http://app:80` with `changeOrigin: false`; no Tent. Why:
  the browser stays on `localhost:8030`, so the PHP session cookie is same-origin
  (`credentials: 'same-origin'`).
- **Security note:** keep `changeOrigin: false`; the PHP session cookie must not pin a
  `Domain`/port, or login through the proxy breaks.
- **Ports:** Vite listens on 8080 *inside* its container (host `${FRONTEND_PORT:-8030}`); `app` is
  host 8080 but `app:80` on the compose network — the proxy target.
- **URL:** Vite `base: '/admin/'` also applies in dev, so the UI is at
  `http://localhost:8030/admin/` (not `:8030/`).
- **First run:** `make build-fe-base build-fe` → `make fe-install` → `make dev-up` → open
  `http://localhost:8030/admin/`.
- **Dependencies:** live in `docker_volumes/node_modules`; the bind mount hides the image's
  install, so `frontend_dev` does not start until `make fe-install` has run.
  `docker_volumes/node_modules/.gitkeep` keeps the folder host-owned (not created by Docker as root).
- **Two UIs:** `:8030/admin/` is the live Vite UI; `:8080/admin/` serves the last
  `make build-fe-release` output (may be stale; absent on a fresh clone → falls through to
  `index.php`, JSON 404). Link [build.md](build.md).

Update `docs/agents/specs/frontend.md`: add a `development.md` row ("Dev environment: images,
`frontend_dev`, Vite proxy, first run") and change the `build.md` description to "Build and
release: `/admin/`, production delivery".

## Files to Change
- `docs/agents/specs/frontend/development.md` — new spec.
- `docs/agents/specs/frontend.md` — index rows.
