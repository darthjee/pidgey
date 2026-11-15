# Frontend Development Environment

Purpose: define how the admin UI is developed locally: images, the `frontend_dev` service, the Vite API proxy, the first run and how dev differs from production.

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
The `*.json` rule uses a regex key (such as `'^/.*\\.json$'`), so every API path, anywhere in
the tree (e.g. `/admin/users/search.json`), reaches the backend instead of Vite (see
[api/conventions/format.md](../api/conventions/format.md#paths)). No Tent (or other separate
proxy) is used.

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

Vite `base: '/'` applies in development as well as in builds, so the dev UI is at
`http://localhost:8030/`. Dev therefore matches the release layout (`/` and `/assets/`, see
[build.md](build.md)) and asset-path bugs show up early.

Rejected alternative: setting `base` only for builds (shorter dev URL, but dev would differ from
the release).

## First run

1. `make build-fe-base build-fe` — build `vite_pidgey-base` and `vite_pidgey`.
2. `make fe-install` — install dependencies into `docker_volumes/node_modules`.
3. `make dev-up` — start the stack, including `frontend_dev`.
4. Open `http://localhost:8030/`.

## Dependencies

Dependencies live in `docker_volumes/node_modules`, bind-mounted into `frontend_dev`. The bind
mount hides whatever the image installed, so on a fresh clone `frontend_dev` does not start until
`make fe-install` has run. `docker_volumes/node_modules/.gitkeep` keeps the folder in the
repository so it is owned by the host user instead of being created by Docker as root.

## Two UIs in development

- `http://localhost:8030/` — the live Vite UI (hot reload). Vite serves `index.html`, the
  source modules and HMR, and proxies `/v1` and `*.json` to `app:80` ([API access](#api-access)).
- `http://localhost:8080/` — the last `make build-fe-release` output, served by the PHP
  static-file handler from `source/static/`
  ([request-handler/static-files.md](../request-handler/static-files.md), [build.md](build.md)).
  It can be stale; on a fresh clone there is no build, so `/` returns a JSON 404.

## Dev server and proxy

**Decision:** development keeps Vite's own dev server and proxy. PHP has no dev mode: it never
proxies to Vite, and there is no `FRONTEND_DEV_MODE` setting.

The origin check passes in dev because the browser sees one origin, `:8030`
(`Sec-Fetch-Site: same-origin`), for both the UI and the proxied API calls (see
[api/conventions/origin-check.md](../api/conventions/origin-check.md)).

### Differences from production

1. **Unknown paths** on `:8030` get Vite's SPA fallback (`index.html`, `200`) instead of
   `302 /#<path>`. Harmless with hash routing; check the real redirect on `:8080`.
2. **Headers:** Vite does not send the static-file handler's headers (`no-cache`, `immutable`,
   `nosniff`, `frame-ancestors`). Test them on `:8080` after `make build-fe-release`.
3. **Fresh clone:** `:8080/` with no build is a JSON 404.

### Rejected alternatives

- **PHP proxies to Vite behind `FRONTEND_DEV_MODE=true`** (Oak's pattern): it needs an HTTP
  client in PHP for dev only, and HMR's WebSocket upgrade cannot be proxied by PHP under Apache.
- **Apache `mod_proxy` to Vite in the dev image:** the dev Apache config would diverge from
  production, which has no vhost access and no `mod_proxy`
  ([request-handler/rewrite.md](../request-handler/rewrite.md#production)).
