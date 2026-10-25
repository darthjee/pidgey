# Plan: Frontend dev setup: vite_pidgey-base image, frontend_dev service, Vite proxy and Makefile

Issue: [17-frontend-dev-setup-vite-pidgey-base-image-frontend-dev-service-vite-proxy-and-makefile.md](../../issues/17-frontend-dev-setup-vite-pidgey-base-image-frontend-dev-service-vite-proxy-and-makefile.md)

## Overview

Make `frontend/` runnable in development, as the #16 specs describe (`docs/agents/specs/frontend/development.md`, `frontend/build.md`, `docker/*.md`). Infra adds:

- the `vite_pidgey-base` image and an arm64-aware `vite_pidgey` image;
- a root `.dockerignore`;
- the `frontend_dev` compose service;
- the `FE_*` Makefile targets (one-off commands use `--no-deps`);
- `.gitignore` and spec updates.

The frontend agent sets the Vite `base` to `/admin/` and adds the `/v1` proxy.

## Agents involved

- [infra](infra.md)
- [frontend](frontend.md)

## Shared contracts

- **Dev server**: `yarn server` (`vite dev --host 0.0.0.0 --port 8080`) listens on **8080 inside the container**. Compose maps host `${FRONTEND_PORT:-8030}` → 8080. Do not change the script's port.
- **Working dir / mounts**: the container's `/home/node/app` is `./frontend`, and `/home/node/app/node_modules` is `./docker_volumes/node_modules`.
- **Proxy**: `/v1` → `http://app:80` with `changeOrigin: false`. `app` is the compose service name, and `frontend_dev` `depends_on: [app]`.
- **Base path**: Vite `base: '/admin/'` in dev and in builds. The dev URL is `http://localhost:8030/admin/`.
- **Build output**: `yarn build` writes `frontend/dist/`. `make build-fe-release` copies `frontend/dist/.` into `source/public/admin` (`FE_RELEASE_DIR`).
- **Checks**: `yarn lint` and `yarn coverage` (existing `package.json` scripts) are what `make tests` runs in `frontend_dev`.
