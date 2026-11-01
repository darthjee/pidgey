# Server

Purpose: describe the production host as the deploy uses it: its folders, the `config/.env`
link, the web directory, the shared `.env` and the host requirements.

Real host names, SSH users, home paths, folder paths and domains are never committed. This
file names them only through CircleCI variables (see [variables.md](variables.md)) and
placeholders: `<live>` for the live folder, `<domain>` for Pidgey's domain.

## Folders

| Folder | Location | Content | Lifetime |
| --- | --- | --- | --- |
| Live | `$SSH_REMOTE_DIR` (`<live>`) | The current release root: the content of `source/` (minus the [exclude list](pipeline.md#exclude-list)), with `vendor/` and the built `public/admin/`. | Replaced on every release by the swap. |
| Staging | `${SSH_REMOTE_TEMP_DIR}-${CIRCLE_WORKFLOW_WORKSPACE_ID}` | The release one workflow is preparing. | Created by `upload_backend`, renamed to live by `release`. A workflow that fails before the swap leaves it behind until removed by hand (see [operations.md](operations.md#removing-stale-staging-folders)). |
| Old live | `<live>_old_<ts>` (`<ts>` is a Unix timestamp) | The previous release, moved aside during the swap. | Exists only for the duration of the swap; removed right after it. No rollback copy is kept. |
| Shared | `$REMOTE_HOME/$SHARED_DIR` | The production `.env`. | Persistent, outside every release. Created by hand by the owner. |

- All four folders stay **outside every web directory of the hosting account**, not just
  outside Pidgey's, so none of them can be served through another site on the same account.
  Only the live release's `public/` is served.
- Staging and live are on the same filesystem, so the swap is a plain rename.
- "Rerun from failed" keeps `CIRCLE_WORKFLOW_WORKSPACE_ID`, so a rerun refills the same staging
  folder.
- There are no other persistent folders. PHP output goes to the host's Apache logs, outside
  the release.

## The `config/.env` link

`config/` already exists in staging after `upload_backend`: it ships the route files
(`routes.php` and `routes/`). `release`'s `link` step adds a symlink beside them:

```
config/.env -> $REMOTE_HOME/$SHARED_DIR/.env
```

It is a symlink, not a copy: one source of truth, independent of the previous release. It is
created before `bin/check-config` and `bin/migrate` run, and it moves to live with the rest of
staging. `config/` is outside `public/`, so the file is never web-reachable. The app's env-file
loader reads `config/.env` from the release root (format in
[architecture/configuration.md](../specs/architecture/configuration.md#env-file)).

The `link` step runs `mkdir -p` on the parent folder before `ln -sfn` (checked in the
darthjee/scripts 0.11.0 source), so an existing `config/` is kept and only the `.env` link is
added.

## Web directory

- In the hosting panel, the web directory of `<domain>` is set to `<live>/public`. `bin/`,
  `config/`, `lib/`, `migrations/` and `vendor/` are therefore not web-reachable.
- Pidgey runs at the root of `<domain>`, with no base path. The admin UI is served from
  `public/admin/` at `/admin/`, same origin as the API.
- The host redirects HTTP to HTTPS.
- `public/.htaccess` returns `404` for any path segment starting with a dot, disables
  directory listings and routes every other non-file request to `index.php`.
- `public/.htaccess` also sends `Strict-Transport-Security: max-age=300` (no
  `includeSubDomains`, no `preload`) on HTTPS responses only. `public/admin/` inherits it, so
  it covers both the API and `/admin/`. The decision is in
  [specs/deployment/security.md](../specs/deployment/security.md#https).
- `public/admin/.htaccess` (from `frontend/public/.htaccess`) serves `index.html` with
  `Cache-Control: no-cache`, so browsers pick up new hashed asset names after a release;
  hashed Vite assets stay cacheable.

## Shared `.env`

- The shared folder is mode `700` and `.env` is mode `600`, both owned by the host user
  (`$SSH_USER`), which is the user PHP runs as.
- It never goes through CI, and the deploy never prints it.
- It holds every app setting, one `KEY=VALUE` per line. Formats, defaults and validation rules
  are in [architecture/configuration.md](../specs/architecture/configuration.md).

Keys it must hold:

| Key | Value |
| --- | --- |
| `PHP_ENV` | `production` |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` (and `DB_PORT` when not `3306`) | The production database. `DB_HOST`, `DB_NAME` and `DB_USER` are required by `bin/check-config`. |
| `DB_MIGRATE_USER`, `DB_MIGRATE_PASSWORD` | Optional. The migration user, used by `bin/migrate` (and `bin/create-db` / `bin/drop-db`). When neither is set, those commands fall back to `DB_USER` / `DB_PASSWORD`. If one is set, both must be (`bin/check-config`). See [operations.md](operations.md#switch-to-separate-db-users). |
| `SECRET_KEY` | At least 32 CSPRNG bytes, hex-encoded (e.g. `openssl rand -hex 32`). Required by `bin/check-config`. |
| `USER_APPROVAL_REQUIRED` | `true` (see [known-gaps.md](known-gaps.md#no-proxy-rate-limiting)). |
| `TRUSTED_PROXY_HOPS` | `0`: no reverse proxy sits in front of Apache on this host (see [known-gaps.md](known-gaps.md#trusted_proxy_hops-not-verified)). |
| `ALLOWED_ORIGINS` or `FRONTEND_BASE_URL` | `https://<domain>`. |

Every other setting may be left out to take its default.

## Host requirements

Confirmed on the host during the first release (#100):

- **PHP 8.4 (8.4.x)** for both the web (set in the hosting panel) and the CLI.
  - The CLI is always called through `$PHP_BIN`, never a bare `php`, which may be another
    version.
  - `bin/check-config` fails the deploy when the CLI is not 8.4.x. The web PHP version cannot
    be checked from the CLI; it is set and checked in the hosting panel.
  - The CLI has `pdo_mysql` and `sodium`.
- **Apache** with `.htaccess` support and `mod_rewrite`.
- **MySQL 8 or newer**, on its own hostname, reachable from the SSH host (migrations run there
  over SSH). Two users on the same database:
  - the **migration user** (`DB_MIGRATE_USER`), used by `bin/migrate`, has `SELECT`, `INSERT`,
    `UPDATE`, `DELETE`, `CREATE`, `DROP`, `INDEX`, `ALTER` and `REFERENCES`;
  - the **app user** (`DB_USER`), used by the web app and `bin/wait-for-db`, has `SELECT`,
    `INSERT`, `UPDATE` and `DELETE` only.

  Until the owner switches production over (see
  [operations.md](operations.md#switch-to-separate-db-users)), `DB_MIGRATE_*` is unset and the
  original user, which holds both sets of rights, serves migrations and the app.
- **OPcache** with `opcache.validate_timestamps` on (the default). Release paths do not change
  on a swap, so PHP must notice the new files.
- **No Composer** on the host: `vendor/` ships with the release.
