# Issue: Frontend: Vite dev proxy for *.json API paths

## Description

Make the Vite dev server (`frontend_dev`, `http://localhost:8030/admin/`) forward every root-relative `*.json` API call to the backend, as `docs/agents/specs/frontend/development.md` (API access) already specifies. Today only `/v1` is proxied. This issue changes the frontend config and the spec notes that point at it. The backend is not touched.

## Problem

`frontend/vite.config.js` proxies only `/v1` to `http://app:80`. The frontend calls root-relative `*.json` paths with no `/v1` prefix:

- `/auth/*.json`
- `/admin/users/*.json`
- `/organization.json`, `/smtp-connections*.json` and `/api-tokens*.json`

In development every one of these calls is served by the Vite server instead of the backend, so login, the admin screens and the Pidgey admin screens do not work through `frontend_dev`.

## Expected Behavior

- Vite proxies `/v1` **and** every `*.json` path to `http://app:80`, both with `changeOrigin: false`.
- The `*.json` rule uses a regex key, `'^/.*\\.json$'`. A regex key takes precedence over Vite's `/admin/` base, so API paths under `/admin/` (such as `/admin/users/search.json`) reach the backend.
- The regex is anchored on `.json` at the end of the URL:
  - The frontend sends no query strings on API calls and does not import any `.json` module, so the strict anchor catches every API call.
  - It does not catch Vite's own module requests (`?import` and similar).
- The `access_token` cookie set through the proxy keeps working on `http://localhost:8030`:
  - The cookie has no `Domain` and no port.
  - The origin check passes because the browser sends `Sec-Fetch-Site: same-origin` (see `docs/agents/specs/api/conventions/origin-check.md`).
- Checked manually in the dev stack: logging in through `http://localhost:8030/admin/` sets the cookie, and a protected call (such as `/admin/users/search.json` or `/organization.json`) succeeds.

## Solution

1. `frontend/vite.config.js`: add the `'^/.*\\.json$'` entry next to `/v1` in `server.proxy`, with `target: 'http://app:80'` and `changeOrigin: false`. Keep the `/v1` entry as is.
2. Spec notes that describe the gap as follow-up #46 now describe the current state:
   - `docs/agents/specs/frontend/development.md` (API access): remove the "Today `frontend/vite.config.js` proxies only `/v1`…" sentence.
   - `docs/agents/specs/frontend/api-compatibility.md` (Cross-cutting behaviour): mark the "Dev server proxies `*.json` paths" row ✅.
   - `docs/agents/specs/api/authentication/local-development.md`: drop the follow-up #46 note.
3. CI: `npm run lint` passes. No Jasmine spec covers `vite.config.js`, and none is added.

Out of scope:
- Backend, CORS or `ALLOWED_ORIGINS` changes.
- Tent or any other separate proxy.
- Changing Vite's `/admin/` base.
- Production serving, where the UI and API already share an origin.

## Benefits

- The dev UI works end to end against the local backend, with the same same-origin cookie behaviour as production.
- The specs and the config agree again.

Context: found while checking frontend/API compatibility in #32 (epic #24).
