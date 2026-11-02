# Frontend Plan: Move the frontend build and deploy to `source/static/`

Main plan: [plan.md](plan.md)

## Shared contracts

- Produce a build whose `index.html` references `/assets/...` (Vite `base: '/'`), with no source maps.
- `frontend/public/` no longer carries an `.htaccess`. The PHP handler sends `no-cache` for `index.html`.

## Implementation Steps

### Step 1 — Base path `/`
In `frontend/vite.config.js`, change `base: '/admin/'` to `base: '/'`. Leave `build.sourcemap` unset: the default is off, and the spec forbids source maps in the release. Keep the `/v1` and `^/.*\\.json$` proxy entries unchanged. The client-side hash routes (`#/admin/users`, `HashRouteResolver.js`) are unrelated to the base path and must not change.

### Step 2 — Remove the `.htaccess`
Delete `frontend/public/.htaccess`. If `frontend/public/` is then empty, delete the folder too, since Vite does not need it. Check that nothing in `frontend/` (specs, ESLint config) references it.

## Files to Change
- `frontend/vite.config.js` — `base: '/'`.
- `frontend/public/.htaccess` — deleted.

## CI Checks
- `frontend`: `yarn lint` and `yarn coverage` (CI job: `frontend_checks`). Also run `yarn build` and check that `dist/index.html` references `/assets/` and that `dist/` holds no `.map` file and no `.htaccess`.

## Notes
- The dev UI moves from `:8030/admin/` to `:8030/`. `docs/agents/specs/frontend/development.md` already says so.
