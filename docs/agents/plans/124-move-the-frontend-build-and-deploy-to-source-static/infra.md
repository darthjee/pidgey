# Infra Plan: Move the frontend build and deploy to `source/static/`

Main plan: [plan.md](plan.md)

## Shared contracts

- `make build-fe-release` replaces `source/static/` with `frontend/dist/`.
- `upload_frontend` uploads `frontend-dist/` into the staging release's `static/` (next to `public/`).
- The production UI is `https://<domain>/`. Files under `static/` are served by `public/index.php` (`StaticFileHandler`), never by Apache directly.

## Steps

- [01 — Build target, CI and gitignore](infra/01-build-ci-gitignore.md)
- [02 — Deployment docs](infra/02-deployment-docs.md)

## CI Checks
- Root: `make build-fe-release`. Then `ls source/static/index.html source/static/assets`, and `git status` shows nothing new under `source/static/`.
- `.circleci/config.yml` must stay valid (`circleci config validate` if available).

## Notes
- `.circleci/backend-exclude.txt` does not list `static/`. Leave it as is: a CI checkout has no `source/static/`, and `upload_frontend` runs after `upload_backend`.
- The release swap replaces the whole live folder, so no host-side cleanup of the old `public/admin/` is needed.
- Enabling gzip on the host is out of scope.
