# Issue: Route every request to index.php

## Description

Part of #72 (Bootstrap a testable request pipeline and CI coverage).

Make Apache hand every request to `source/public/index.php`, so the app has a single entry point, by adding `source/public/.htaccess` exactly as specified in `docs/agents/specs/request-handler/rewrite.md`.

## Problem

There is no `.htaccess` today, so only `/` and `/index.php` reach PHP; any other path is an Apache HTML 404.

## Expected Behavior

- `/`, `/anything/deep?x=1`, any method → reaches `index.php`; the query string is preserved and `REQUEST_URI` keeps the original path.
- `/.env`, `/.git/config` and any path segment starting with `.` → plain Apache 404 in dev (existing dotfiles get a 403 from the host in production), never reaching PHP.
- `Authorization` header → available to PHP as `$_SERVER['HTTP_AUTHORIZATION']`; unset (not `""`) when absent.
- Existing files/directories under `source/public/` are served by Apache directly (none exist yet); a directory without an index → 403 (no listing).

## Solution

Add `source/public/.htaccess` with **exactly** these rules (from `rewrite.md`):

```apache
CGIPassAuth On
Options -Indexes
RewriteEngine On
RewriteRule (^|/)\. - [R=404,L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

No Dockerfile change and no `BASE_VERSION` bump.

### Dev image — no change needed

- The stock `php:8.4-apache` image enables `conf-enabled/docker-php.conf` (`<Directory /var/www/> Options -Indexes / AllowOverride All`), included after `apache2.conf`'s `AllowOverride None` block, so it wins.
- `dockerfiles/dev_pidgey-base/Dockerfile` already `sed`s `/var/www/` → `${APACHE_DOCUMENT_ROOT}` over `conf-available/*.conf` (giving `<Directory /home/app/app/public> AllowOverride All`) and runs `a2enmod rewrite`.
- Verified locally with stock `php:8.4-apache` + the same steps + this `.htaccess`: `/` and `/anything/deep?x=1` → 200 from `index.php`; `/.env`, `/.git/config` → 404; `Authorization: Bearer abc` → `HTTP_AUTHORIZATION`. Same approach as Tent.
- Rejected: a dedicated `conf-available/pidgey.conf` or an extra `sed` — redundant with the stock config.
- `CGIPassAuth On` and `Options -Indexes` are redundant in dev (mod_php passes `Authorization`; `docker-php.conf` sets `-Indexes`) but required in production (`cgi-fcgi`), so the same file serves both.

### Production hosting — verified

The `.htaccess` was tested verbatim in a temporary folder on the production DreamHost server (Apache 2.4.58, PHP `cgi-fcgi`), then removed. The whole file is accepted (no 500), so `FileInfo`, `AuthConfig` and `Options` overrides are granted.

| Request | Result |
|---|---|
| `GET /`, `GET /anything/deep?x=1`, `POST /foo` | 200 from `index.php`; original `REQUEST_URI` and method |
| `Authorization: Bearer abc123` | `HTTP_AUTHORIZATION = "Bearer abc123"`; no `REDIRECT_HTTP_AUTHORIZATION` |
| no `Authorization` header | `HTTP_AUTHORIZATION` unset |
| existing `real.txt` | served directly |
| existing dotfile `.env` | 403 from the host, before our rule |
| non-existing `.nope`, `x/.git/config`, `.well-known/x` | 404 from our dotfile rule |
| existing empty directory | 403 (no listing) |

Dotfiles answer 404 in dev and 403 (existing ones) in production; both keep them away from PHP — accepted.

### Performance & security

Covered by `rewrite.md` ("Security", "Performance", "Edge cases"): dotfiles → 404 (no existence leak; `/.well-known/` blocked until a future ACME setup carves it out); listing disabled explicitly; only `index.php` runs (all PHP stays outside `source/public/`); per-request `.htaccess` lookup and `stat`s are negligible.

Future deployment work (not this issue): the production domain's web directory must point at the deployed `public/` folder, not the project root, or `lib/`, `vendor/` and `composer.json` become reachable.

### Out of scope

- The handler logic in `index.php` (the `RequestHandler` sub-issue of #72).
- The production `pidgey` image (future work per `docs/agents/specs/docker/images.md`).
- The CircleCI image (PHPUnit runs without Apache).

### Testing

No automated Apache/smoke test. The user verifies routing manually (e.g. `curl` through `docker compose up app`).

### CI failures are not blocking for this issue

CircleCI is already red on `main` (`backend_checks` and `backend_tests` fail; `frontend_checks` passes), and `main` has no branch protection. For this issue:

- The PR may be merged with failing CircleCI jobs. Do not wait for CI to go green and do not fix unrelated CI failures in this PR.
- Do not change `.circleci/config.yml` to hide the failures (no `|| true`, no removed jobs).
- During implementation, for each failing area, create a separate GitHub issue to fix it — one for the backend (covering `backend_checks` / `backend_tests`) and one for the frontend if `frontend_checks` fails — with the failing job names, CircleCI links and the relevant error output. Link them from this issue's PR.

### Responsible agent

infra

## Benefits

- Single entry point: every dynamic path reaches the front controller, unblocking the request handler work.
- One `.htaccess` that behaves the same in dev and on the production host, already verified on both.
