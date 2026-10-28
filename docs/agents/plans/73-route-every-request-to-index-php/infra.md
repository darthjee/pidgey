# Infra Plan: Route every request to index.php

Main plan: [plan.md](plan.md)

## Overview

Single entry point for the app: Apache rewrites every request that is not an existing file or directory to `source/public/index.php`, blocks dotfiles with a 404, disables directory listing and passes the `Authorization` header to PHP.

## Context

- Today there is no `.htaccess`; only `/` and `/index.php` reach the placeholder `index.php` (`{"status":"ok"}`).
- The dev image already honors `.htaccess`: the stock `php:8.4-apache` `conf-enabled/docker-php.conf` has `AllowOverride All` for `/var/www/`, and `dockerfiles/dev_pidgey-base/Dockerfile` already `sed`s it to `/home/app/app/public` and runs `a2enmod rewrite`. Verified locally and on the production DreamHost host (see the issue). **Do not touch the Dockerfiles and do not bump `BASE_VERSION`.**
- CircleCI is already red on `main` (`backend_checks`, `backend_tests`); `main` has no branch protection. The PR may merge with failing jobs.

## Implementation Steps

### Step 1 — Add `source/public/.htaccess`

Create `source/public/.htaccess` with exactly these lines, in this order (copied from `rewrite.md`, no additions):

```apache
CGIPassAuth On
Options -Indexes
RewriteEngine On
RewriteRule (^|/)\. - [R=404,L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

Check that `.htaccess` is not excluded by `.gitignore` / `.dockerignore` so it is committed and mounted. Do not modify `index.php` (owned by the `RequestHandler` sub-issue of #72).

Optional sanity check (not required, the user verifies manually): `docker compose up app` and `curl` `/`, `/anything/deep?x=1` (→ 200 `{"status":"ok"}`), `/.env` with a file present (→ 404), and a request with `Authorization: Bearer x`.

### Step 2 — Open one fix issue per failing CI area

Do **not** fix CI failures in this PR and do **not** edit `.circleci/config.yml` to hide them (no `|| true`, no removed jobs).

After pushing the PR, check the CircleCI jobs on it. For each failing area, create a GitHub issue (with `gh issue create`), then reference the created issues in the PR description:

- **Backend** — if `backend_checks` and/or `backend_tests` fail: one issue (e.g. "Fix failing backend CircleCI jobs") listing the failing job names, CircleCI links and the relevant error output.
- **Frontend** — only if `frontend_checks` fails: one issue (e.g. "Fix failing frontend CircleCI job") with the same details.

If every job passes, create no issue.

## Files to Change

- `source/public/.htaccess` — new file, the rewrite rules above.

## CI Checks

Not blocking for this issue (see Step 2). For reference:
- `source/`: `docker compose run --rm tests composer lint` (CI job: `backend_checks`), `docker compose run --rm tests composer coverage` (CI job: `backend_tests`)
- `frontend/`: `npm run lint`, `npm run coverage` (CI job: `frontend_checks`)

## Notes

- Expected dotfile behavior differs by environment and is accepted: 404 (our rule) in dev, 403 from the host for existing dotfiles in production.
- `CGIPassAuth On` and `Options -Indexes` are redundant in dev but required in production (`cgi-fcgi`); keep them so one file serves both.
- `/.well-known/` is blocked by the dotfile rule; fine until a future ACME setup.
- Future deployment work (out of scope): the production domain's web directory must point at the deployed `public/` folder.
