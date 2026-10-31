# Release Flow

Purpose: define, step by step, how a semver tag becomes the live release on the production
host, which deploy-script actions each job uses and which CircleCI variables it needs.

The jobs, their images, filters and order are in [pipeline.md](pipeline.md). The folders on
the host are in [server.md](server.md); failure handling is in [edge-cases.md](edge-cases.md).

## Overview

1. A semver tag (`1.2.3`, no `v` prefix) is pushed and the check jobs pass.
2. `upload_backend` fills the staging folder with the backend.
3. `upload_frontend` adds the built admin UI under `public/admin/`.
4. `release` links the shared `.env`, checks the configuration, runs migrations, swaps
   staging into live and removes stale staging folders.

Nothing reaches the live folder until step 4's swap. Every step before the swap only touches
the staging folder, so any failure before it leaves the live release untouched.

## Deploy script

Every deploy job uses the shared `deploy_frontend.sh` from the `darthjee/scripts` image (not a
per-project copy), copied into the deploy images (see
[docker/images.md](../docker/images.md#deploy-tooling)).

- **Version:** `0.11.0`, which provides fail-fast behaviour, quoted paths, the per-workflow
  staging folder, `DEPLOY_PATH`, optional `SSH_KNOWN_HOSTS`, `upload` with `EXCLUDE_FROM`,
  `build` with `BUILD_COMMAND`, `link` inside staging, the generic remote `run`, and a
  `release` that refuses to run when staging is missing.
- **`cleanup`** needs `0.12.0`. Pidgey targets `0.12.0`; if it is not released in time,
  deploys start on `0.11.0` without the `cleanup` step and stale staging folders are removed
  by hand until the upgrade.

Actions Pidgey uses:

| Action | Used by | What it does |
| --- | --- | --- |
| `build` | `upload_frontend` | Runs `BUILD_COMMAND` locally (the Vite build). |
| `upload` | `upload_backend`, `upload_frontend` | rsyncs local files into the staging folder, under `DEPLOY_PATH` when set, skipping the paths listed in `EXCLUDE_FROM`. |
| `link` | `release` | Creates a symlink inside staging (`ln -sfn`), pointing at a path outside the release. |
| `run` | `release` | Runs a command on the host, inside the staging folder. |
| `release` | `release` | Checks that staging exists, then swaps it into the live folder. |
| `cleanup` | `release` | Removes other staging folders older than one day. |

## Staging folder

Each workflow gets its own staging folder on the host:
`${SSH_REMOTE_TEMP_DIR}-${CIRCLE_WORKFLOW_WORKSPACE_ID}`.

- It is on the same filesystem as the live folder, so the swap is a plain rename.
- "Rerun from failed" keeps `CIRCLE_WORKFLOW_WORKSPACE_ID`, so a rerun refills the same folder.
- Its layout is the release root: the content of the repository's `source/` folder.

## Backend upload (`upload_backend`)

1. Keep only `source/` as the job's working directory.
2. `composer install --no-dev` with PHP 8.4. Composer is not on the host, so `vendor/` ships
   with the release.
3. `upload` the working directory into the staging root, with `EXCLUDE_FROM` pointing at
   `.circleci/backend-exclude.txt`.

The exclude list keeps out of the release:

- `tests/` and `coverage/`;
- lint and phpdoc configuration (`phpcs.xml`, `phpcs/`, `phpmd*.xml`, `phpdoc.dist.xml`) and
  `phpunit.xml`;
- any `.env*` file;
- `.git`.

What ships: `bin/`, `lib/`, `migrations/`, `public/`, `vendor/`, `composer.json` and
`composer.lock`. `config/` holds only the `.env` link, created in the `release` job.

## Frontend upload (`upload_frontend`)

1. Keep only `frontend/` as the job's working directory.
2. `yarn install --frozen-lockfile`.
3. `build` with `vite build` (Vite `base: '/admin/'`, see
   [frontend/build.md](../frontend/build.md)), producing `dist/`.
4. `upload` `dist/` with `DEPLOY_PATH=public/admin/`, so it lands in the staging folder's
   `public/admin/`.

The build output includes `public/admin/.htaccess`, which sends `Cache-Control: no-cache` for
`index.html`; hashed Vite assets stay cacheable (see
[frontend/build.md](../frontend/build.md#admin-serving)).

## Release (`release`)

All steps run against the staging folder, in this order. A failing step stops the job.

1. **`link`** the shared `.env` (`$REMOTE_HOME/<shared folder>/.env`) to `config/.env` in
   staging. It is a symlink, not a copy: one source of truth, independent of the previous
   release.
2. **`run` `$PHP_BIN bin/check-config`**: loads `config/.env` and runs the boot-time
   configuration checks plus the PHP 8.4.x check (see
   [architecture/configuration.md](../architecture/configuration.md#bincheck-config)). A
   failure aborts the deploy before the swap.
3. **`run` `$PHP_BIN bin/migrate`**: applies pending migrations against the production
   database, using the staging `.env`. A failure aborts the deploy before the swap (see
   [architecture/database.md](../architecture/database.md) for the compatibility rule).
4. **`release`**: checks that staging exists, then swaps it into the live folder: remove any
   old backup, move live aside, move staging to live, remove the old folder. No rollback copy
   is kept.
5. **`cleanup`** (from `0.12.0`): removes other staging folders older than one day. Recent
   ones may belong to a deploy in progress, so they are kept.

Scripts on the host always use `$PHP_BIN`, never a bare `php` (see
[server.md](server.md#host-requirements)).

## CircleCI variables

All deploy variables live in the `pidgey-deploy` CircleCI context, attached only to the deploy
jobs (see [security.md](security.md#ci-secrets)). Their values are never committed.

| Variable | Meaning | Required | Secret |
| --- | --- | --- | --- |
| `SSH_HOST` | Host name of the production SSH server. | Yes | No (kept private) |
| `SSH_PORT` | SSH port of that server. | Yes | No |
| `SSH_USER` | Host user the deploy logs in as (the user PHP runs as). | Yes | No (kept private) |
| `SSH_PRIVATE_KEY` | Private half of the dedicated deploy key. | Yes | Yes |
| `SSH_KNOWN_HOSTS` | `known_hosts` line(s) for the host, from a verified `ssh-keyscan`. The deploy jobs fail if it is unset. | Yes | No (integrity-critical) |
| `SSH_REMOTE_DIR` | Live folder on the host (the release root the web directory's `public/` lives in). | Yes | No (kept private) |
| `SSH_REMOTE_TEMP_DIR` | Prefix of the staging folders; the workflow workspace id is appended. | Yes | No (kept private) |
| `REMOTE_HOME` | Home folder of the host user, under which the shared folder lives. | Yes | No (kept private) |
| `PHP_BIN` | Absolute path of the PHP 8.4 CLI on the host. | Yes | No |

"Kept private" values are not credentials, but they describe the host and are never written
into the repository.

**App settings are never CircleCI variables.** `SECRET_KEY`, `DB_*`, `PHP_ENV`,
`USER_APPROVAL_REQUIRED` and every other setting read by the app live only in the shared
`.env` on the host (see [server.md](server.md#shared-folder)). CI never sees them.

## Verification

There is no automated test of the deploy workflow itself: it only runs against the real host.
It is verified by:

- **the `darthjee/scripts` test suite**, which covers the script's behaviour;
- **the pre-swap guards**: `SSH_KNOWN_HOSTS` set, `bin/check-config`, `bin/migrate`, and the
  `release` check that staging exists;
- **a post-release smoke check**, the manual first-release checklist (#100):
  - the HTTPS API answers;
  - `/admin/` loads;
  - `bin/migrate --status` is clean;
  - `bin/`, `vendor/` and `config/` are not web-reachable.

## Pending decisions

None.
