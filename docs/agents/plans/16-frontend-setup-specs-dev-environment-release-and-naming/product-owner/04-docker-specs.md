# Update the docker specs

**`docker/images.md`**
- Add rows: `vite_pidgey-base` (`dockerfiles/vite_pidgey-base/Dockerfile`; `darthjee/node` base,
  yarn cache pre-warmed via `darthjee/scripts` `yarn_builder.sh`, `CMD ["npm", "run", "server"]`;
  built from the **repo root** context since it copies `frontend/package.json`/`yarn.lock`;
  `<version>`/`<version>-arm64` tags; built and pushed manually with `make build-fe-base` /
  `make push-fe-base`) and `vite_pidgey` (`dockerfiles/vite_pidgey/Dockerfile`,
  `FROM vite_pidgey-base:<version><BASE_SUFFIX>`, repo-root context; used by `frontend_dev`).
- Resolve the "Node.js/Yarn" pending decision: separate frontend image (not `dev_pidgey-base`).
- `pidgey` (production) row: built frontend goes in the web root's `admin/`; link the production
  delivery pending decision in [frontend/build.md](../frontend/build.md) instead of restating it.

**`docker/compose.md`**
- `frontend_dev` row: image `vite_pidgey`, port `${FRONTEND_PORT:-8030}` → 8080, drop "Future
  work"; Vite dev server proxying `/v1` to `app:80`; link [frontend/development.md](../frontend/development.md).
- `app` row: "static frontend" → "the built frontend at `/admin/`".
- `tests` row: drop "/ frontend tests" (frontend checks run in `frontend_dev`).
- Notes: `frontend_dev` mounts `./frontend` as its working dir (no backend anchor); add
  `FRONTEND_PORT` (8030) to the port-override list; 8030 already listed as non-assumption.

**`docker/volumes.md`**
- `./frontend` → `/home/node/app` and `./docker_volumes/node_modules` → `/home/node/app/node_modules`:
  drop "Future work".
- **Delete** the `./frontend/dist → <web root>/static` row (the release build lands in
  `source/public/admin`, already served via the `./source` mount).

**`docker/makefile.md`**
- Targets: `build-fe-base`, `push-fe-base`, `build-fe`, `fe-install`
  (`docker compose run --rm frontend_dev yarn install`), `build-fe-release` (`yarn build` in
  `frontend_dev`, then replace `$(FE_RELEASE_DIR)` with `frontend/dist/.`).
- `dev-up`: includes `frontend_dev` (drop "future work").
- `tests`: appends `docker compose run --rm frontend_dev sh -c "yarn lint && yarn coverage"`.
- Variables: `FE_BASE_VERSION` (`0.0.1`), `FE_BASE_IMAGE` (`$(DOCKER_ID_USER)/vite_$(PROJECT)-base`),
  `FE_IMAGE` (`$(DOCKER_ID_USER)/vite_$(PROJECT)`), `FE_RELEASE_DIR` (`source/public/admin`).
- First run: add `make build-fe-base && make build-fe` and `make fe-install`; frontend at
  `http://localhost:8030/admin/` (link [frontend/development.md](../frontend/development.md)).

## Files to Change
- `docs/agents/specs/docker/images.md`
- `docs/agents/specs/docker/compose.md`
- `docs/agents/specs/docker/volumes.md`
- `docs/agents/specs/docker/makefile.md`
