# Deployment docs
Replace every `public/admin/` and `/admin/` UI reference under `docs/agents/deployment/` with the new layout. Running `grep -rn admin docs/agents/deployment` must find no UI reference afterwards.

- `server.md`:
  - Folders table, Live row: "with `vendor/` and the built `public/admin/`" → "with `vendor/` and the built frontend in `static/`".
  - Web directory: the UI is served at `/` by the PHP static-file handler from `<live>/static/`, which is outside the web directory (link `../specs/request-handler/static-files.md`). Remove the `public/admin/.htaccess` bullet. Say instead that `index.html` gets `Cache-Control: no-cache` and `/assets/*` gets `immutable` from the handler.
  - HTTPS: replace "`public/admin/` inherits the same `.htaccess` ... static admin files get it too" with: the UI's files are served through `public/index.php`, so they get the header like any API response.
- `pipeline.md`:
  - The `upload_frontend` job row → "into the staging folder's `static/`".
  - The deploy-chain prose (around "uploads it into `public/admin/`") → `static/`.
  - The `deploy_frontend.sh upload` row → `DEPLOY_PATH=static/`, "into staging's `static/`".
  - Release content: drop "(with the built admin UI)" from `public/`, and add that `static/` (the built frontend, from `upload_frontend`) ships next to it and is not web-reachable.
- `operations.md`:
  - Verify-the-release list: "`https://<domain>/admin/` loads" → "`https://<domain>/` loads", and the second HSTS `curl` targets `https://<domain>/`.
  - Add: `curl -sI https://<domain>/does-not-exist` shows `302` and `Location: /#/does-not-exist`.
  - Add: `curl -sI -H 'Accept-Encoding: gzip' https://<domain>/assets/<bundle>.js` (a bundle name from the built `index.html`) shows `Content-Encoding: gzip`. If it does not, record it in `known-gaps.md`; fixing it is out of scope.
  - The `.env` rotation step (around line 123): `/admin/` → `/`.
- `known-gaps.md`: no change now. The gzip entry is only added if the post-release check fails.

## Files to Change
- `docs/agents/deployment/server.md`
- `docs/agents/deployment/pipeline.md`
- `docs/agents/deployment/operations.md`
