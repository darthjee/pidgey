# Deploy jobs and workflow

Add five jobs to `.circleci/config.yml` and wire them into the `main` workflow (spec: `docs/agents/specs/deployment/pipeline.md#deploy-jobs`).

## Build jobs (no context)

- **`build_backend`** (`circleci_pidgey-base:0.0.2`):
  1. `prepare_source`.
  2. Copy `/home/circleci/vendor` to `./vendor` (as `composer_setup` does).
  3. `composer install --no-dev --no-interaction --no-progress --optimize-autoloader`.
  4. Move the result into a `backend/` folder and `persist_to_workspace` (`root: .`, `paths: [backend]`).

  Alternatively, persist from a dedicated folder so the workspace layout is `backend/...`.
- **`build_frontend`** (`darthjee/circleci_node:0.2.1`):
  1. Set up `frontend/` as `frontend_checks` does.
  2. `yarn install --frozen-lockfile`.
  3. `yarn build` (`vite build`).
  4. `persist_to_workspace` `dist/` (e.g. move it to `frontend-dist/` first, so the two build outputs don't collide in the workspace).

## Context jobs (image `circleci_pidgey-base:0.0.2`; context and serial group set in the workflow)

They install and build nothing.

- **`upload_backend`**:
  1. `checkout`, only to get `.circleci/backend-exclude.txt`. No dependencies are installed.
  2. `attach_workspace` at `/tmp/workspace`.
  3. `deploy_setup`.
  4. `deploy_frontend.sh upload` with `SOURCE=/tmp/workspace/backend/` (the trailing slash uploads the contents into the staging root) and `EXCLUDE_FROM=.circleci/backend-exclude.txt`.
- **`upload_frontend`**:
  1. `attach_workspace` at `/tmp/workspace`.
  2. `deploy_setup`.
  3. `deploy_frontend.sh upload` with `SOURCE=/tmp/workspace/frontend-dist/` and `DEPLOY_PATH=public/admin/`.
- **`release`**, in order. Each step is a separate `run`, so a failure stops the job:
  1. `deploy_setup`.
  2. `link`: `SOURCE="$REMOTE_HOME/$SHARED_DIR/.env"`, `DEPLOY_PATH=config/.env`.
  3. `run`: `COMMAND="$PHP_BIN bin/check-config"`.
  4. `run`: `COMMAND="$PHP_BIN bin/migrate"`.
  5. `release`.

  No `cleanup` step (#106).

Set per-step environment variables inline, e.g. `DEPLOY_PATH=public/admin/ SOURCE=... deploy_frontend.sh upload`, or with the step's `environment:` key. Context variables are expanded by the shell.

## Workflow (`main`)

Add the deploy jobs. Each one gets `filters: *deploy_tags`.

- `build_backend` and `build_frontend` both require `[backend_checks, backend_tests, frontend_checks]`.
- `upload_backend` requires `[build_backend]`.
- `upload_frontend` requires `[build_frontend, upload_backend]`.
- `release` requires `[upload_backend, upload_frontend]`.
- Give the workflow entries of the three context jobs only both `context: pidgey-deploy` and `serial-group: << pipeline.project.slug >>/pidgey-deploy`. CircleCI sets both keys at the workflow-job level.

Leave the existing check jobs and `coverage_final` unchanged, apart from the image bump.

## Files to Change

- `.circleci/config.yml`: the five deploy jobs and the workflow wiring.
