# Routing, rewrite and entry-point specs

Update the request-routing specs to the new model.

- `routing.md`:
  - **Path patterns / Router**: add the prefix matcher (e.g. a `/assets/*` pattern matching any path beginning with `/assets/`; the handler reads the rest of the path); the exact API is the implementer's call, the behavior is fixed.
  - **GET fallback**: a final route for unmatched `GET`, tried after every other route, applied only to extensionless paths (last segment has no `.`) outside `/v1/`; everything else keeps the JSON 404. Replace "There is no fallback handler class" accordingly.
  - **Route files**: add `routes/frontend.php` (registered so the fallback is last).
  - **Edge cases**: unknown `*.json`, `GET /v1/*`, non-GET → JSON 404; `HEAD` does not fall back to `GET`; `/favicon.ico`, `/robots.txt`, `/index.html`, `/index.php` → JSON 404; `/assets` and `/assetsX` not matched by the prefix → redirected; query ignored for matching.
  - **Testing**: routes-table test covers prefix routes and the fallback.
  - Opening paragraph: paths end in `.json` except `POST /v1/messages` and the frontend routes.
- `rewrite.md`: "Paths that bypass PHP" no longer lists the UI (`/admin/`, `/admin/assets/*`, the `mod_dir` redirect); the UI is served by PHP from `source/static/`. Update the `*.json` paragraph, the "`/admin/` on a fresh clone" edge case (now `/` → JSON 404), and the production verification bullets that mention `/admin/` (mark them as historical results of the 2026-10-27 probe or restate for `/`). Dotfile 404 stays.
- `architecture/backend.md#entry-point`: the UI is served at `/` by the PHP static-file handler from `source/static/`; `*.json` always reaches the API; `POST /v1/messages` is the only non-`.json` API route; other extensionless GETs redirect to the SPA.
- `api/conventions/format.md`: `*.json` always reaches the API anywhere in the tree; non-`.json` GET paths belong to the frontend (drop "under `/admin/`").

## Files to Change

- `docs/agents/specs/request-handler/routing.md` — prefix matcher, GET fallback, route file, edge cases, testing.
- `docs/agents/specs/request-handler/rewrite.md` — UI no longer bypasses PHP; edge cases.
- `docs/agents/specs/architecture/backend.md` — entry-point routing rule.
- `docs/agents/specs/api/conventions/format.md` — paths section.
