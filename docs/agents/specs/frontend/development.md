# Frontend Development Environment

Purpose: define how the admin UI is developed locally: images, the `frontend_dev` service, the Vite API proxy and the first run.

Image, service, volume and Makefile-target definitions live in the docker specs; this file
describes the workflow and links to them.

## Images

- `vite_pidgey-base` — Node (based on `darthjee/node`) with the yarn cache pre-warmed by
  `darthjee/scripts` `yarn_builder.sh`; built with the repository root as build context.
- `vite_pidgey` — built on top of `vite_pidgey-base`; the image used by `frontend_dev`.

Definitions, tags and build targets: [docker/images.md](../docker/images.md) and
[docker/makefile.md](../docker/makefile.md).

## Service

`frontend_dev` runs the Vite dev server (`yarn server`) with `./frontend` and
`./docker_volumes/node_modules` mounted, and depends on `app`. Definition:
[docker/compose.md](../docker/compose.md); mounts: [docker/volumes.md](../docker/volumes.md).

## API access

Vite proxies `/v1` **and** every `*.json` path to `http://app:80` with `changeOrigin: false`.
The `*.json` rule uses a regex key (such as `'^/.*\\.json$'`), which takes precedence over
Vite's own `/admin/` base, so API paths under `/admin/` (e.g. `/admin/users/search.json`)
reach the backend (see [api/conventions/format.md](../api/conventions/format.md#paths)). No
Tent (or other separate proxy) is used. The `frontend/vite.config.js` change is done by a
later implementation issue.

Why: the browser stays on `localhost:8030` for both the UI and the API, so the `access_token`
cookie is same-origin and the client layer can use `credentials: 'same-origin'`
([layout.md](layout.md)), exactly as in production where the UI and API share an origin.

**Security note:** keep `changeOrigin: false`, and the `access_token` cookie must not pin a
`Domain` or port; otherwise the cookie set through the proxy is rejected and login breaks.
The cookie is `Secure` even over plain HTTP in dev; see
[api/authentication/local-development.md](../api/authentication/local-development.md) for why
this works on `localhost` and its caveats.

## Ports

| Where | Vite (`frontend_dev`) | API (`app`) |
| --- | --- | --- |
| Host | `${FRONTEND_PORT:-8030}` | 8080 |
| Inside the container / compose network | 8080 | `app:80` (the proxy target) |

## URL

Vite `base: '/admin/'` applies in development as well as in builds, so the dev UI is at
`http://localhost:8030/admin/` (not `http://localhost:8030/`). Dev therefore matches the release
layout ([build.md](build.md)) and asset-path bugs show up early.

Rejected alternative: setting `base` only for builds (shorter dev URL, but dev would differ from
the release).

## First run

1. `make build-fe-base build-fe` — build `vite_pidgey-base` and `vite_pidgey`.
2. `make fe-install` — install dependencies into `docker_volumes/node_modules`.
3. `make dev-up` — start the stack, including `frontend_dev`.
4. Open `http://localhost:8030/admin/`.

## Dependencies

Dependencies live in `docker_volumes/node_modules`, bind-mounted into `frontend_dev`. The bind
mount hides whatever the image installed, so on a fresh clone `frontend_dev` does not start until
`make fe-install` has run. `docker_volumes/node_modules/.gitkeep` keeps the folder in the
repository so it is owned by the host user instead of being created by Docker as root.

## Two UIs in development

- `http://localhost:8030/admin/` — the live Vite UI (hot reload).
- `http://localhost:8080/admin/` — the last `make build-fe-release` output served by Apache
  ([build.md](build.md)). It can be stale; on a fresh clone there is no build, so the request
  falls through to `index.php` and returns a JSON 404.
