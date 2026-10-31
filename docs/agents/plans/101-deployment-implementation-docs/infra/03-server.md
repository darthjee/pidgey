# Server
Create `docs/agents/deployment/server.md`, using placeholders only:
- the folders:
  - live: `$SSH_REMOTE_DIR`;
  - staging: `${SSH_REMOTE_TEMP_DIR}-${CIRCLE_WORKFLOW_WORKSPACE_ID}`;
  - the swap's `<live>_old_<ts>`;
  - shared: `$REMOTE_HOME/$SHARED_DIR`;
  
  plus their lifetimes, and the requirement that all of them stay outside every web directory of the hosting account;
- the `config/.env` symlink created by `release`'s `link` step, outside `public/`;
- the web directory set to `<live>/public` in the hosting panel. Pidgey runs at the domain root. HTTP → HTTPS is redirected by the host. `public/.htaccess` returns 404 for dotfiles, and `public/admin/.htaccess` serves `index.html` with `no-cache`;
- the shared `.env`:
  - the folder is mode `700` and the file `600`, owned by the host user;
  - the keys it must hold, linked to `specs/architecture/configuration.md` for the formats;
  - `TRUSTED_PROXY_HOPS=0`, because there is no reverse proxy in front of Apache;
- host requirements as confirmed in #100: PHP 8.4 for web and CLI (CLI through `PHP_BIN`), MySQL 8+ with the grants listed, OPcache `validate_timestamps` on, and no Composer on the host.

## Files to Change
- `docs/agents/deployment/server.md` — new.
