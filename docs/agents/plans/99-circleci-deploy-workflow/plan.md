# Plan: CircleCI deploy workflow

Issue: [99-circleci-deploy-workflow.md](../../issues/99-circleci-deploy-workflow.md)

## Overview

Add the semver-tag deploy chain to `.circleci/config.yml`. The chain has two kinds of jobs:

- **Build jobs** (`build_backend`, `build_frontend`) run without the deploy context and hand their output over through the CircleCI workspace.
- **Context jobs** (`upload_backend`, `upload_frontend`, `release`) use `pidgey-deploy`, run one at a time and only call `deploy_frontend.sh` (`darthjee/scripts:0.11.0`).

The change also adds:

- the backend exclude list;
- the admin `index.html` no-cache `.htaccess`, shipped through Vite's `public/`;
- a new `SHARED_DIR` context variable, recorded in the specs.

All `circleci_pidgey-base` jobs move to `0.0.2`. There is no `cleanup` step until #106.

## Agents involved

- [product-owner](product-owner.md)
- [infra](infra.md)
- [frontend](frontend.md)

## Shared contracts

- **`SHARED_DIR`**: a new `pidgey-deploy` context variable.
  - It holds the shared folder's name relative to `$REMOTE_HOME`. It is required and kept private, but it is not a secret.
  - The shared `.env` lives at `$REMOTE_HOME/$SHARED_DIR/.env`.
  - The `release` job links it to `config/.env` in staging.
- **Admin `.htaccess`**:
  - The frontend agent creates `frontend/public/.htaccess`.
  - `vite build` copies it to `dist/.htaccess`.
  - `upload_frontend` uploads `dist/` with `DEPLOY_PATH=public/admin/`, so it lands at `public/admin/.htaccess` in the release.
  - Infra adds nothing for it beyond uploading `dist/`.
- **`deploy_frontend.sh` 0.11.0 interface** (from `darthjee/docker` `scripts/0.11.0/home/sbin/deploy_frontend.sh`):
  - `generate_key_file`: writes `SSH_PRIVATE_KEY`, and `SSH_KNOWN_HOSTS` when set, to `$HOME`.
  - `upload`: needs `SOURCE`; optional `DEPLOY_PATH` and `EXCLUDE_FROM`. Runs `rsync -avz` into `${SSH_REMOTE_TEMP_DIR}-${CIRCLE_WORKFLOW_WORKSPACE_ID}[/DEPLOY_PATH]`.
  - `link`: `SOURCE` is the link target and `DEPLOY_PATH` is the link path inside staging. It creates the parent folder.
  - `run`: runs `COMMAND` inside staging. The caller handles quoting.
  - `release`: checks that staging exists, then swaps it into `SSH_REMOTE_DIR`. The old copy is removed unless `KEEP_OLD=true`.
  - Without `SSH_KNOWN_HOSTS`, the script falls back to `StrictHostKeyChecking=no`. That is why the jobs must fail early when it is unset.
