# Build target, CI and gitignore
Move the release target out of the web root.

- `Makefile`: `FE_RELEASE_DIR?=source/public/admin` → `FE_RELEASE_DIR?=source/static`. Keep the empty-value guard, the `rm -rf` and the `cp -R frontend/dist/. $(FE_RELEASE_DIR)/` as they are.
- `.circleci/config.yml`, `upload_frontend`: `DEPLOY_PATH=public/admin/` → `DEPLOY_PATH=static/`.
- `.gitignore`: replace `source/public/admin/` with `source/static/`. Do not add a Makefile cleanup for a stale `source/public/admin/` in dev clones: once it is no longer ignored, `git status` lists it as untracked, which flags it for deletion.

## Files to Change
- `Makefile` — `FE_RELEASE_DIR`.
- `.circleci/config.yml` — `upload_frontend` `DEPLOY_PATH`.
- `.gitignore` — ignore `source/static/`, drop `source/public/admin/`.
