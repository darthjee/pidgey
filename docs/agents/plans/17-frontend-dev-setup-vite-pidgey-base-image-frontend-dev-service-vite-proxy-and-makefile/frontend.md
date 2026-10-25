# Frontend Plan: Frontend dev setup: vite_pidgey-base image, frontend_dev service, Vite proxy and Makefile

Main plan: [plan.md](plan.md)

## Shared contracts

- The dev server listens on 8080 inside the container (`yarn server`). Keep `port: 8080`, `host: '0.0.0.0'`.
- Proxy `/v1` → `http://app:80`, `changeOrigin: false`.
- `base: '/admin/'` in dev and in builds. `yarn build` still writes to `frontend/dist/`.

## Implementation Steps

### Step 1 — Vite base and API proxy

In `frontend/vite.config.js`:
- Add `base: '/admin/'` at the top level.
- Under `server`, add `proxy: { '/v1': { target: 'http://app:80', changeOrigin: false } }`.
- Remove `cors: true`. The UI and the API share an origin through the proxy, so CORS isn't needed.

Keep `port`, `host`, `strictPort` and the React plugin as they are.

## Files to Change

- `frontend/vite.config.js` — `base`, `server.proxy`, drop `cors`.

## Notes

- `changeOrigin: false` must stay as it is. The PHP session cookie (added later) relies on the `Host` staying `localhost:8030` (see `docs/agents/specs/frontend/development.md`).
- The citadel client code still calls citadel paths. Adapting it to `/v1` is out of scope.
