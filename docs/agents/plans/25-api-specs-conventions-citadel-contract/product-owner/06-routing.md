# Align routing: backend rule and Vite dev proxy
Apply the "`*.json` is always the API" decision outside the API specs:

- `architecture/backend.md` ("Entry point"): replace "`/admin/` is reserved for the UI and the
  API router never claims it" with: non-`.json` paths under `/admin/` are reserved for the UI;
  `*.json` paths are always routed to the API (Apache already rewrites non-existing files to the
  front controller, so no rewrite change is needed). Mention that `POST /v1/messages` is the
  only prefixed route.
- `frontend/development.md` ("API access"): Vite proxies `/v1` **and** every `*.json` path
  (regex key such as `'^/.*\\.json$'`, which takes precedence over Vite's own `/admin/` base) to
  `http://app:80` with `changeOrigin: false`. Replace the PHP-session wording with the
  `access_token` cookie (same-origin requirement unchanged; the cookie must not pin a `Domain`
  or port). Note that the `vite.config.js` change is done by a later implementation issue.

## Files to Change
- `docs/agents/specs/architecture/backend.md` — `/admin/` routing rule.
- `docs/agents/specs/frontend/development.md` — Vite proxy and cookie wording.
