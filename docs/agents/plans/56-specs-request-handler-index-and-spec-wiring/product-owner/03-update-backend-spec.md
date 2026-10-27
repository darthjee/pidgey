# Update architecture/backend.md

## Entry point

Rewrite the `## Entry point` section of `docs/agents/specs/architecture/backend.md` as a short summary that links to `../request-handler.md` as the source of truth for the request flow.

The summary **must keep** these rules. `frontend/build.md` relies on the first two, and #57/#59 will link back to them:
- A single front controller (`source/public/index.php`) receives every dynamic request. Apache rewrites requests that are **not existing files or directories** to it, so static files, including the built frontend at `/admin/`, never reach PHP.
- `*.json` paths are always routed to the API, anywhere in the tree, including under `/admin/`. The UI never ships `.json` files under `/admin/`.
- API paths are root-relative with no prefix. `POST /v1/messages` is the only prefixed route (link `../api/conventions/format.md#paths`, as today).

Move only the mechanics (bootstrapping configuration, PDO and logger, then dispatching) out of this section, replacing them with the link. Keep the existing links to `frontend/build.md` and `api/conventions/format.md`. No inbound link uses an anchor into `backend.md`, so restructuring the section breaks nothing.

## Layers table

In the `HTTP` row, replace `Request/Response objects, router, controllers, JSON encoding, authentication guards (...)` with wording that uses **request handlers** and **middlewares**, and link `../request-handler.md`. For example: `Request/Response objects, router, request handlers, middlewares (authentication guards: cookie-session gate and Bearer token, see ...), JSON encoding. See [request-handler.md](../request-handler.md). No SQL, no SMTP.` Keep the existing link to `api/authentication.md`.

## Files to Change
- `docs/agents/specs/architecture/backend.md` — shortened "Entry point" (keeping the three routing rules) and the terminology fix in the Layers table.
