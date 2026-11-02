# Plan: Move the frontend build and deploy to `source/static/`

Issue: [124-move-the-frontend-build-and-deploy-to-source-static.md](../../issues/124-move-the-frontend-build-and-deploy-to-source-static.md)

## Overview
Point the frontend build, the dev server and the deploy at `source/static/` (outside the web root) instead of `source/public/admin/`, and serve the UI at `/` through `StaticFileHandler` (#123). Remove the now-redundant `frontend/public/.htaccess`, and update every non-spec doc that still describes the UI at `/admin/`. The specs already describe the target state (#121).

**Depends on #123.** This branch must merge after `StaticFileHandler` and `routes/frontend.php` are on `main`, or production loses the UI. Before opening the PR, merge `origin/main` and check that `source/config/routes/frontend.php` exists.

## Agents involved

- [frontend](frontend.md)
- [infra](infra.md)
- [architect](architect.md)

## Shared contracts

- **Release folder:** `source/static/` in dev (`make build-fe-release`) and `static/` at the release root in production (`upload_frontend`, next to `public/`). It holds `index.html` at its root and the hashed bundles under `assets/`.
- **Base path:** Vite `base: '/'`, so the built `index.html` references `/assets/<name>-<hash>.<ext>`. Those are exactly the paths `StaticFileHandler` serves.
- **URLs:** release UI `http://localhost:8080/`, dev UI `http://localhost:8030/`, production `https://<domain>/`. `/admin/` is no longer a UI path. Being extensionless, it gets `302 Location: /#/admin/`.
- **Caching:** `Cache-Control: no-cache` for `index.html` now comes from the PHP handler, not from an `.htaccess`.
