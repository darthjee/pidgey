# Server

Purpose: describe the production host as the deploy uses it: its folders, the `config/.env`
link, the web directory, the shared `.env`, the host requirements, the database users and
HTTPS.

Real host names, SSH users, home paths, folder paths and domains are never committed: not in
specs, docs, CI config or code. This file names them only through CircleCI variables (see
[variables.md](variables.md)) and placeholders: `<live>` for the live folder, `<domain>` for
Pidgey's domain.

Why: the repository is public. Host facts are not credentials, but publishing them would map
the hosting account for an attacker, and they change without any code change.

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
- The live folder existed before the first deploy, so the swap needs no first-release special
  case.
- There are no other persistent folders. PHP output goes to the host's Apache logs, outside
  the release. A future persistent folder would live in the shared folder and be added to
  each release with another `link` step, like `config/.env`.

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
- `public/.htaccess` also sends `Strict-Transport-Security` on HTTPS responses only (see
  [HTTPS](#https)).
- `public/admin/.htaccess` (from `frontend/public/.htaccess`) serves `index.html` with
  `Cache-Control: no-cache`, so browsers pick up new hashed asset names after a release;
  hashed Vite assets stay cacheable.

## Shared `.env`

- The shared folder is mode `700` and `.env` is mode `600`, both owned by the host user
  (`$SSH_USER`), which is the user PHP runs as.
- It never goes through CI, and the deploy never prints it.
- It is linked into each release at `config/.env`, outside `public/` (see
  [The `config/.env` link](#the-configenv-link)).
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

Why: the `.env` holds the only real secrets of the deployment (`SECRET_KEY`, the database
passwords). Modes `700` / `600` keep other users of the host out; keeping it outside every
release and outside `public/` means no web request can read it and no release can overwrite
it; keeping it out of CI means a CircleCI compromise or a careless log line cannot leak it.
`TRUSTED_PROXY_HOPS` must match the real number of `X-Forwarded-For` hops: a value that is too
high lets clients spoof their IP.

## Host requirements

Confirmed on the host during the first release (#100):

- **PHP 8.4 (8.4.x)** for both the web (set in the hosting panel) and the CLI.
  - The CLI is always called through `$PHP_BIN`, never a bare `php`, which may be another
    version.
  - `bin/check-config` fails the deploy when the CLI is not 8.4.x. The web PHP version cannot
    be checked from the CLI; it is set and checked in the hosting panel.
  - The CLI has `pdo_mysql` and `sodium`.
  - `composer.json`'s `^8.4` still allows newer versions in development; production is pinned
    to 8.4.x so the code runs on the version it is tested with.
- **Apache** with `.htaccess` support and `mod_rewrite`.
- **MySQL 8 or newer**, on its own hostname, reachable from the SSH host (migrations run there
  over SSH), with a migration user and an app user on the same database (see
  [Database users](#database-users)). `GET_LOCK` needs no privilege.
- **OPcache** with `opcache.validate_timestamps` on (the default). Release paths do not change
  on a swap, so PHP must notice the new files.
- **No Composer** on the host: `vendor/` ships with the release.

The checks done on the host during #100 (web PHP version, web directory, no folder under any
web directory, MySQL version, grants, OPcache) all passed; they are not repeated on each
release. `bin/check-config` repeats the CLI PHP check on every deploy. Whether a reverse proxy
sits in front of Apache is still unverified (see
[known-gaps.md](known-gaps.md#trusted_proxy_hops-not-verified)).

## Database users

Decision (#109): production uses **two MySQL users** on the same database, so the web app runs
without DDL rights. Which entry point uses which user is in
[architecture/database.md](../specs/architecture/database.md#database-users).

| User | Settings | Grants on the app database | Used by |
| --- | --- | --- | --- |
| Migration user | `DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` | `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `DROP`, `INDEX`, `ALTER`, `REFERENCES` | `bin/migrate`, `bin/create-db`, `bin/drop-db` |
| App user | `DB_USER` / `DB_PASSWORD` | `SELECT`, `INSERT`, `UPDATE`, `DELETE` only | the web app, `bin/wait-for-db` |

- `REFERENCES` is needed because migrations create foreign keys.
- Because the migration user has `DROP`, **`bin/drop-db` must never be run in production**;
  it exists only for development and test resets (`make db-reset`).
- Both users' credentials live only in the [shared `.env`](#shared-env); nothing about them is
  committed.
- The code supports the split from the release that ships #109 on. Until the owner switches
  production over (see [operations.md](operations.md#switch-to-separate-db-users) for the
  steps and their order), `DB_MIGRATE_*` is unset and the original user, which holds both sets
  of rights, serves migrations and the app.

Why: SQL issued by the app (for example through an injection flaw) cannot alter or drop the
schema when the app user has no DDL rights. The split does not protect against a flaw that
reads the `.env` (see [known-gaps.md](known-gaps.md#migration-credentials-in-the-shared-env)).

## HTTPS

The host redirects HTTP to HTTPS.

Decision (#110, raised in #115): `public/.htaccess` (from `source/public/.htaccess`) sends
`Strict-Transport-Security: max-age=86400` on HTTPS responses only.

- It covers the API and the admin UI: `public/admin/` inherits the same `.htaccess`. Because
  the web server sets it, static admin files get it too.
- It is sent only over HTTPS; plain-HTTP responses (including local docker and CI, which serve
  plain HTTP) never carry it.
- There is no `includeSubDomains` and no `preload`, because other subdomains of the domain are
  not owned by Pidgey: forcing HTTPS on them could break sites Pidgey does not control.
- `max-age=86400` (one day) is the second step of a staged rollout: `max-age=300` came first
  (#110) and was checked on the host, then #115 raised it to one day. A short `max-age` caps
  any lockout after an HTTPS failure (an expired certificate, a host migration) at 24 hours.
  One year comes in a later step.
- Verification after a release is in [operations.md](operations.md#release).
