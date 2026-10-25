# Infra Plan: Frontend dev setup: vite_pidgey-base image, frontend_dev service, Vite proxy and Makefile

Main plan: [plan.md](plan.md)

## Shared contracts

- `frontend_dev`:
  - container port 8080, host `${FRONTEND_PORT:-8030}`;
  - `working_dir: /home/node/app`;
  - mounts `./frontend` and `./docker_volumes/node_modules`;
  - `depends_on: [app]`;
  - `command: yarn server`.
- The frontend agent provides the `/v1` → `app:80` proxy and `base: '/admin/'`. `yarn build` writes `frontend/dist/`.
- `make tests` runs `yarn lint && yarn coverage` in `frontend_dev`.

## Steps

- [01 — Frontend images and .dockerignore](infra/01-images-and-dockerignore.md)
- [02 — frontend_dev compose service](infra/02-compose-service.md)
- [03 — Makefile targets](infra/03-makefile.md)
- [04 — .gitignore and spec updates](infra/04-gitignore-and-specs.md)

## Notes

- Building the images needs network access to Docker Hub (`darthjee/node:0.2.1`, `darthjee/scripts:0.8.0`). Pushing `vite_pidgey-base` is out of scope.
- `frontend_dev` won't start on a fresh clone until `make fe-install` has filled `docker_volumes/node_modules`. This is expected and documented.
- Containers run as uid 1000 (`node`). Files that `build-fe-release` copies from `frontend/dist` may need a `chown` on Linux hosts with another uid.
- CI (`frontend_checks`) is #18. The frontend agent doc and `check_frontend.sh` are #19.
