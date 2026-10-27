# Plan: Frontend: Vite dev proxy for *.json API paths

Issue: [46-frontend-vite-dev-proxy-for-json-api-paths.md](../../issues/46-frontend-vite-dev-proxy-for-json-api-paths.md)

## Overview
Make `frontend_dev` forward every root-relative `*.json` API call to the backend, as `docs/agents/specs/frontend/development.md` (API access) specifies, and bring the spec notes in line with the new config.

## Context
`frontend/vite.config.js` proxies only `/v1`. The clients in `frontend/assets/js/client/` call `/auth/*.json`, `/admin/users/*.json`, `/organization.json`, `/smtp-connections*.json` and `/api-tokens*.json`, so in development those calls are served by Vite instead of the backend. None of these calls carries a query string, and the frontend imports no `.json` module, so a regex anchored on `.json` at the end of the URL catches every API call and none of Vite's own module requests (`?import` and similar). A regex key (starting with `^`) takes precedence over Vite's `base: '/admin/'`, so `/admin/users/*.json` reaches the backend too.

## Implementation Steps

### Step 1 — Add the `*.json` proxy rule
In `frontend/vite.config.js`, add an entry to `server.proxy` next to `/v1`:

```js
'^/.*\\.json$': {
  target: 'http://app:80',
  changeOrigin: false,
},
```

Keep the `/v1` entry unchanged and keep `changeOrigin: false` on both (the `access_token` cookie must stay same-origin on `localhost:8030`; see the security note in `development.md`).

### Step 2 — Update the spec notes
- `docs/agents/specs/frontend/development.md` (API access): remove the sentence "Today `frontend/vite.config.js` proxies only `/v1`; adding the `*.json` rule is follow-up #46 (see …)". The rest of the section already describes the target state.
- `docs/agents/specs/frontend/api-compatibility.md` (Cross-cutting behaviour, line ~114): set the "Dev server proxies `*.json` paths to the backend" row's status to ✅ (e.g. "✅ (`'^/.*\\.json$'` regex key next to `/v1`, `changeOrigin: false`; see [development.md](development.md#api-access))").
- `docs/agents/specs/api/authentication/local-development.md` (line ~27): drop the "adding the `*.json` rule is follow-up #46" note so it states that Vite proxies `/v1` and every `*.json` path.

## Files to Change
- `frontend/vite.config.js` — add the `'^/.*\\.json$'` proxy entry.
- `docs/agents/specs/frontend/development.md` — remove the follow-up #46 sentence.
- `docs/agents/specs/frontend/api-compatibility.md` — mark the dev-proxy row ✅.
- `docs/agents/specs/api/authentication/local-development.md` — drop the follow-up #46 note.

## CI Checks
- `frontend`: `npm run lint` (CI job: `frontend_checks`)
- `frontend`: `npm run coverage` (CI job: `frontend_checks`)

## Notes
- `npm run lint` runs `eslint assets specs`, so it does not cover `vite.config.js`; keep the file's existing style (single quotes, trailing commas, 2-space indent).
- No Jasmine spec covers `vite.config.js`, and none is added.
- Manual check in the dev stack (`make dev-up`): log in at `http://localhost:8030/admin/`, confirm the `access_token` cookie is set, and confirm a protected call (`/admin/users/search.json` or `/organization.json`) answers from the backend.
- No backend, CORS, `ALLOWED_ORIGINS` or Vite `base` changes.
