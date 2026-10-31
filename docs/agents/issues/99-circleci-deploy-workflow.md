# Issue: CircleCI deploy workflow

## Description

Part of epic #95. Add the tag-triggered production deploy chain to `.circleci/config.yml`, following the deployment specs (`docs/agents/specs/deployment/pipeline.md`, `release.md`, `security.md`), the shared `deploy_frontend.sh` from `darthjee/scripts`, and the CI base image `circleci_pidgey-base:0.0.2` from #98, which carries the deploy tooling.

Depends on #97 (`bin/check-config`) and #98 (CI images), both merged. Requires a `security` agent review before merge.

## Problem

The specs define a deploy chain, but CI has no deploy jobs, so a semver tag deploys nothing. The original issue text describes a single backend job that installs dependencies and uploads them. The specs have since split the chain into **build jobs**, which run without the deploy context, and **context jobs**, which hold the deploy key. That split keeps third-party Composer and Yarn code away from the deploy key. The implementation must follow the specs, not the original text.

## Expected Behavior

Pushing a semver tag (`1.2.3`, no `v` prefix) on a commit where all check jobs pass builds the backend and admin UI, uploads both into a per-workflow staging folder on the host, links the shared `.env`, runs `bin/check-config` and `bin/migrate`, then swaps staging into live. If any step before the swap fails, the live release stays untouched. Branch pushes, `main` included, never deploy.

## Solution

### Deploy chain (`.circleci/config.yml`)

Applies to every deploy job:

- Runs only on tags matching `/^\d+\.\d+\.\d+$/`, with all branches ignored.
- Requires `backend_checks`, `backend_tests` and `frontend_checks`, directly or through the jobs it requires.

Jobs:

| Job | Context | Image | Steps |
| --- | --- | --- | --- |
| `build_backend` | none | `circleci_pidgey-base:0.0.2` | Keep only `source/`, run `composer install --no-dev` (PHP 8.4), then `persist_to_workspace` the result, `vendor/` included. |
| `build_frontend` | none | `darthjee/circleci_node:0.2.1` | Keep only `frontend/`, run `yarn install --frozen-lockfile` and `vite build`, then `persist_to_workspace` `dist/`. |
| `upload_backend` | `pidgey-deploy` | `circleci_pidgey-base:0.0.2` | `attach_workspace`, then `deploy_frontend.sh upload` into the staging root with `EXCLUDE_FROM=.circleci/backend-exclude.txt`. |
| `upload_frontend` | `pidgey-deploy` | `circleci_pidgey-base:0.0.2` | `attach_workspace`, then `upload` `dist/` with `DEPLOY_PATH=public/admin/`. |
| `release` | `pidgey-deploy` | `circleci_pidgey-base:0.0.2` | In staging: `link` `$REMOTE_HOME/$SHARED_DIR/.env` to `config/.env`, `run` `$PHP_BIN bin/check-config`, `run` `$PHP_BIN bin/migrate`, then `release`. |

Job order:

- `build_backend` and `build_frontend` run in parallel.
- `upload_backend` requires `build_backend`.
- `upload_frontend` requires `build_frontend` and `upload_backend`, so the two uploads never write to staging at the same time.
- `release` requires both uploads.

Context jobs:

- Share the `serial-group` `pidgey-deploy`.
- Run `deploy_frontend.sh generate_key_file`.
- Check that `SSH_KNOWN_HOSTS` is set before any SSH call and fail otherwise.
- Install and build nothing.

No `cleanup` step: `darthjee/scripts:0.12.0` is not released yet, so stale staging folders are removed by hand. Adding the step is tracked in #106.

### Image version

Every job that uses `circleci_pidgey-base` moves to `0.0.2`, the check jobs (`backend_checks`, `backend_tests`) included, so the config uses a single version. Prefer one shared executor.

### Shared folder variable

New `pidgey-deploy` context variable `SHARED_DIR`: the shared folder's name, relative to `$REMOTE_HOME`. It is required and kept private. In the same change, add it to the CircleCI variables table in `docs/agents/specs/deployment/release.md` and replace the `<shared folder>` placeholder in the deployment specs (`release.md`, `server.md`, `pipeline.md`) with `$SHARED_DIR`, so the specs match the config.

### Backend exclude list (`.circleci/backend-exclude.txt`)

New file. It excludes `tests/`, `coverage/`, `phpcs.xml`, `phpcs/`, `phpmd*.xml`, `phpdoc.dist.xml`, `phpunit.xml`, `.env*` and `.git`. What ships: `bin/`, `lib/`, `migrations/`, `public/`, `vendor/`, `composer.json` and `composer.lock`.

### Admin cache header (`frontend/public/.htaccess`)

New file in Vite's static `public/` folder, so Vite copies it into `dist/`. It sends `Cache-Control: no-cache` for `index.html`; hashed assets keep their default caching. `source/public/admin/` stays git-ignored.

### Out of scope

- Setting up the CircleCI context and its restriction (semver-tag expression, security group). The owner does this by hand in CircleCI.
- The first-release smoke checklist (#100).
- The `cleanup` step and the `darthjee/scripts:0.12.0` bump (#106).

## Benefits

- Releases become one `git tag` push, with pre-swap guards (config check, migrations, staging check).
- The deploy key never runs alongside third-party package code.
- Serialized deploys and per-workflow staging folders prevent concurrent releases from clobbering each other.
