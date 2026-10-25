# Issue: Frontend dev setup: vite_pidgey-base image, frontend_dev service, Vite proxy and Makefile

## Description

Part of #15. This turns `frontend/` (copied from citadel in #14) into a working development setup. It builds what the specs merged in #16 describe: a Node base image, the `frontend_dev` compose service, the Vite proxy to the PHP `app`, the Makefile targets and the release into `source/public/admin`. Names and decisions come from `docs/agents/specs/frontend/development.md`, `frontend/build.md` and `docs/agents/specs/docker/*.md`. The layout follows citadel, adapted to pidgey's one-Dockerfile-per-image convention.

## Problem

- `dockerfiles/vite_pidgey/Dockerfile` builds `FROM darthjee/vite_pidgey-base`, but that image has no Dockerfile and no build target. Its `FROM` also ignores `BASE_SUFFIX`, so arm64 hosts can't build it.
- `docker-compose.yml` has no frontend service, and the Makefile has no frontend targets. `make dev-up` and `make tests` ignore the frontend completely.
- `frontend/vite.config.js` has no `base` and no proxy, and keeps citadel's `cors: true`. The UI can't reach `/v1`, and its asset paths don't match the `/admin/` release.
- The repo root has no `.dockerignore`. A repo-root build context would send `docker_volumes/` (mysql_data and vendor, about 145MB, later also node_modules) to every build, and mysql-owned files can break the build.
- `.gitignore` doesn't ignore `source/public/admin/` yet (interim rule in `frontend/build.md`). It also still has citadel-only `backend/` lines.

## Expected Behavior

- On a fresh clone, the First run steps work: `make build-fe-base build-fe`, then `make fe-install`, then `make dev-up`. After that, `http://localhost:8030/admin/` serves the live Vite UI.
- A `/v1/...` request made from `:8030` reaches `app` through the Vite proxy.
- `make tests` runs backend lint and tests, then frontend `yarn lint && yarn coverage`, and all of it passes.
- `make build-fe-release` fills `source/public/admin/`, Apache serves it at `http://localhost:8080/admin/`, and git shows no untracked files afterwards.
- One-off frontend commands (`fe-install`, the frontend half of `tests`, `build-fe-release`) don't start `app` or `mysql`.

## Solution

Owner: **infra**. The `vite.config.js` change belongs to the **frontend** agent.

- **`dockerfiles/vite_pidgey-base/Dockerfile`** (new; build context = repo root):
  ```dockerfile
  FROM darthjee/scripts:0.8.0 AS scripts
  FROM darthjee/node:0.2.1 AS base
  USER node

  FROM base AS builder
  ENV HOME_DIR=/home/node
  COPY --chown=node:node frontend/package.json frontend/yarn.lock /home/node/app/
  USER root
  COPY --chown=node:node --from=scripts /home/scripts/builder/yarn_builder.sh /usr/local/sbin/yarn_builder.sh
  RUN /bin/bash yarn_builder.sh

  FROM base
  ENV HOME_DIR=/home/node
  COPY --chown=node:node --from=builder /home/node/yarn/new/ /usr/local/share/.cache/yarn/v6/
  USER node
  CMD ["npm", "run", "server"]
  ```
- **`dockerfiles/vite_pidgey/Dockerfile`**: keep the existing second yarn warm-up, so a changed `yarn.lock` only needs a cheap `make build-fe` and not a base rebuild. Add `ARG BASE_SUFFIX=""` and use `FROM darthjee/vite_pidgey-base:${BASE_VERSION}${BASE_SUFFIX}`. Change the `BASE_VERSION` default from `latest` to `0.0.1`.
- **`.dockerignore`** (new, repo root): `.git`, `docker_volumes/`, `frontend/node_modules`, `frontend/dist`, `frontend/coverage`, `frontend/.nyc_output`, `source/vendor`, `source/coverage`. The `dev_pidgey*` builds use the `source/` context, so they are not affected.
- **`docker-compose.yml`**: add
  ```yaml
    frontend_dev:
      image: darthjee/vite_pidgey
      build:
        context: .
        dockerfile: dockerfiles/vite_pidgey/Dockerfile
        args:
          BASE_VERSION: ${FE_BASE_VERSION:-0.0.1}
          BASE_SUFFIX: ${BASE_SUFFIX:-}
      working_dir: /home/node/app
      volumes:
        - ./frontend:/home/node/app
        - ./docker_volumes/node_modules:/home/node/app/node_modules
      ports:
        - "${FRONTEND_PORT:-8030}:8080"
      depends_on:
        - app
      command: yarn server
  ```
  plus `docker_volumes/node_modules/.gitkeep`.
- **`frontend/vite.config.js`** (frontend): set `base: '/admin/'` and `server.proxy` = `{ '/v1': { target: 'http://app:80', changeOrigin: false } }`, and drop `cors: true`.
- **`Makefile`**:
  - New variables: `FE_BASE_VERSION=0.0.1`, `FE_BASE_IMAGE=$(DOCKER_ID_USER)/vite_$(PROJECT)-base`, `FE_IMAGE=$(DOCKER_ID_USER)/vite_$(PROJECT)`, `FE_RELEASE_DIR?=source/public/admin`.
  - `build-fe-base`: `docker build -f dockerfiles/vite_$(PROJECT)-base/Dockerfile -t $(FE_BASE_IMAGE):$(FE_BASE_VERSION)$(BASE_SUFFIX) .`
  - `push-fe-base`.
  - `build-fe`: passes `--build-arg BASE_VERSION=$(FE_BASE_VERSION) --build-arg BASE_SUFFIX=$(BASE_SUFFIX)` and uses the `.` context.
  - `fe-install`: `docker compose run --rm --no-deps frontend_dev yarn install`.
  - `build-fe-release`: `docker compose run --rm --no-deps frontend_dev yarn build`, then replace `$(FE_RELEASE_DIR)` with `frontend/dist/.` (`rm -rf`, `mkdir -p`, `cp -R`).
  - `dev-up` adds `frontend_dev`.
  - `tests` appends `docker compose run --rm --no-deps frontend_dev sh -c "yarn lint && yarn coverage"`.
  - Update `.PHONY`.
- **`.gitignore`**: add `source/public/admin/` (interim rule from `frontend/build.md`), and drop the citadel-only `backend/coverage/` and `backend/report/` lines.
- **Specs**: in `docs/agents/specs/docker/makefile.md`, add `--no-deps` to the `fe-install`, `tests` and `build-fe-release` rows. Mention the root `.dockerignore` in `docker/images.md`, next to the repository-root context.

### Constraint (not verified here)

The PHP session cookie must not pin a `Domain` or port, and the proxy keeps `changeOrigin: false`, so that login works through `:8030` (see `frontend/development.md`). No session code exists yet, so this is checked by the issue that adds sessions, not by this one.

### Out of scope

CI (#18), the frontend agent (#19), pushing images to Docker Hub, adapting the citadel client layer to `/v1`, the production frontend decision, and an `/admin/` `.htaccess`.

### Testing

- `make build-fe-base build-fe`, `make fe-install`, `make dev-up`, then open `http://localhost:8030/admin/`.
- A `/v1` request from `:8030` reaches `app`. Any response from `index.php`, including a JSON 404, shows the proxy works.
- `make tests` passes, including frontend lint and coverage.
- `make build-fe-release` fills `source/public/admin/`, `http://localhost:8080/admin/` serves it, and `git status` stays clean.

## Benefits

- One command set builds and runs the admin UI next to the API. Dev uses the same origin and the same `/admin/` base as the release.
- Frontend lint and coverage become part of `make tests`, which is the definition of done.
- Faster, smaller and more reliable repo-root Docker builds.
