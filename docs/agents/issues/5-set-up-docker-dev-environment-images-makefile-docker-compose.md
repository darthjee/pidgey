# Issue: Set up Docker dev environment (images, Makefile, docker-compose)

## Description

Part of the backend bootstrap Epic (#3). Create the Docker-based development environment so
that **nothing runs on the host**: Composer, PHP and (later) PHPUnit/phpcs/phpmd all run inside
containers via Docker Compose v2. Follows the specs in `docs/agents/specs/docker/` (updated by
the specs sub-issue) and the Tent repository pattern (`darthjee/tent`).

Depends on: the specs update sub-issue (#4, merged).

## Problem

The repository has only specs and agent configuration. There are no Dockerfiles, compose file,
Makefile or `composer.json`. So there is nowhere to run PHP, Composer or (later) the linters
and tests, and every following sub-issue (#6 lint/tests, #7 DB/migrations, #8 CI) is blocked.

## Expected Behavior

- On a fresh clone, the documented steps build the images and
  `docker compose run --rm tests composer install` succeeds.
- `docker compose up app` serves the stub `{"status":"ok"}` JSON response at `http://localhost:8080/`.
- `mysql`, `mailpit` and `phpmyadmin` start and are reachable on their ports.

## Solution

- **`dockerfiles/dev_pidgey-base/Dockerfile`**: `php:8.4-apache`; non-root `app` user (uid
  1000) with home `/home/app/app`; apt `unzip git curl libzip-dev $PHPIZE_DEPS`; extensions
  `zip pdo pdo_mysql` and `pcov`; `a2enmod rewrite`; global Composer; Apache web root symlinked
  to `/home/app/app/public` (the mounted `source/public/`); `PATH` includes
  `/home/app/app/vendor/bin`; multi-stage `builder` that bakes a `vendor/` from `composer.*`.
  Built for amd64 (`<version>`) and arm64 (`<version>-arm64`).
- **`dockerfiles/dev_pidgey/Dockerfile`**: `ARG BASE_SUFFIX=""`,
  `FROM darthjee/dev_pidgey-base:<BASE_VERSION>${BASE_SUFFIX}`, copies `composer.*`, runs
  `composer install` and `composer dump-autoload`.
- **`Makefile`**: variables `PROJECT=pidgey`, `BASE_VERSION`, `DOCKER_ID_USER`, `BASE_SUFFIX`;
  targets:
  - `build-base` / `push-base`: build and push `dev_pidgey-base` (manual).
  - `build`: build `dev_pidgey`.
  - `dev`: `docker compose run --rm tests bash` (shell in the `tests` service, with
    `mysql` and `mailpit` started).
  - `dev-up`: `docker compose up` for `app`, `mysql`, `phpmyadmin`, `mailpit`.
  - The spec's `tests` target is added in the lint/tests sub-issue (#6), and the `ci-*`
    wrappers in the CircleCI sub-issue (#8), once the commands they run exist.
  - Update `docs/agents/specs/docker/makefile.md` so the `dev` row says it opens a shell in
    the `tests` service.
- **`docker-compose.yml`** with YAML anchors:
  - `base_build` builds `dev_pidgey` (so a fresh clone works).
  - `app`: Apache on host 8080 → 80, `PHP_ENV=development`.
  - `tests`: `PHP_ENV=test`, depends on `base_build`, `mysql`, `mailpit`.
  - `mysql` (`mysql:9`, data in `docker_volumes/mysql_data`, port 3306), `mailpit`
    (`axllent/mailpit`, UI 8025, SMTP 1025), `phpmyadmin` (8040).
  - Volumes: `./source` → `/home/app/app`, `./docker_volumes/vendor` → `/home/app/app/vendor`.
- **`.env`** (committed; development-only values, never real secrets), loaded by the compose
  services through `env_file`:
  - For the `mysql:9` image: `MYSQL_ROOT_PASSWORD`, `MYSQL_DATABASE=pidgey_development`,
    `MYSQL_USER`, `MYSQL_PASSWORD`. The image creates the development database and user on the
    first start (an empty `docker_volumes/mysql_data`).
  - For the application, read later by #7's configuration: `DB_HOST=mysql`, `DB_PORT=3306`,
    `DB_NAME=pidgey_development`, `DB_USER`, `DB_PASSWORD`, `SMTP_HOST=mailpit`,
    `SMTP_PORT=1025`.
  - The test database name and its creation are left to #7, whose create-db script stays
    idempotent for both databases.
- **`.claude/agents/infra.md`**: change the secrets rule so that a committed `.env` may hold
  only development-only placeholder values, and real secrets are never committed.
- **`.gitignore`**: `docker_volumes/*` except placeholder files, `source/vendor/`,
  `source/coverage`, `source/.phpunit.result.cache`, `source/complexity.html`.
- **`docker_volumes/`** placeholder files for `mysql_data/` and `vendor/`.
- **Minimal `source/composer.json`** (needed because both Dockerfiles run `composer install`):
  PSR-4 `Pidgey\\` → `lib/`; `require`: `php` `^8.4` and `monolog/monolog`; `require-dev`:
  `phpunit/phpunit`, `squizlabs/php_codesniffer`, `slevomat/coding-standard`, `phpmd/phpmd`
  (not `phpdocumentor/phpdocumentor`: it depends on `league/flysystem` 1.x, which Composer
  blocks for security advisories; how phpdocumentor runs is deferred to #6); `allow-plugins` for
  `dealerdirect/phpcodesniffer-composer-installer`. Commit `composer.lock`. Lint/test configs
  and composer scripts come in the next sub-issue.
- **Stub `source/public/index.php`**: a throwaway placeholder that returns a hard-coded
  `{"status":"ok"}` with `Content-Type: application/json`, only to check that Apache + PHP
  serve the mounted web root. It is not an API feature: no spec entry and no tests. It is
  replaced when the real front controller is built. No routing and no `.htaccess`: the image
  only enables `mod_rewrite`, and the rewrite rules come with the front controller.
- **Docs**: first-run steps (`export DOCKER_ID_USER=darthjee && make build-base && make build`,
  `docker compose run --rm tests composer install`, `docker compose up app`) and update
  `docs/agents/folder-structure.md` (drop *(planned)* for what now exists).

### Alternative solutions

- **Vendor handling: follow Tent as-is.**
  - The base image's `builder` stage bakes a `vendor/` from `composer.*`, and `dev_pidgey` runs
    `composer install`. This keeps the image self-contained and makes the dev layer's install
    incremental.
  - Compose still mounts `./docker_volumes/vendor` over `/home/app/app/vendor`. That keeps
    `vendor/` out of `source/` and keeps it between runs, but it hides the baked copy. So the
    first-run `docker compose run --rm tests composer install` stays.
  - Rejected:
    - Dropping the baking: lighter images, but it contradicts `images.md`.
    - Dropping the volume: every dependency change would need `make build`.

### Edge cases

- Fresh clone with the base image not yet on Docker Hub (or `BASE_VERSION` bumped locally):
  covered by the documented `make build-base && make build`.
- Apple Silicon: use the `-arm64` base via `BASE_SUFFIX=-arm64`. This works through both
  `make build` and compose: `base_build` passes `BASE_SUFFIX` from the environment as a build
  arg (`BASE_SUFFIX=-arm64 docker compose build`).
- Host file ownership: containers run as `app` (uid 1000), the same as Tent. Docs only: on a
  Linux host with a different uid, files written to `source/` or `docker_volumes/` may need a
  `chown`.
- Changing `MYSQL_*` in `.env` after the first start has no effect, because the image only
  reads those variables on an empty data directory. Document the reset: stop `mysql`, empty
  `docker_volumes/mysql_data` (keep the placeholder file), then start it again.
- MySQL readiness: `depends_on` only waits for the container to start, not for MySQL to
  accept connections. #5 has no DB code, so nothing is needed here; waiting for the database
  is #7's wait-for-db script.

### Out of scope

- Node.js/Yarn, production `pidgey` image, image release jobs, CI.
- Makefile `tests` (#6) and `ci-*` (#8) targets.
- Test database creation, DB configuration code, migrations (#7).
- Front controller, routing and `source/public/.htaccess` rewrite rules; a permanent health endpoint.
- phpcs/phpmd/phpunit configuration and composer scripts (#6).

## Benefits

- Nothing runs on the host: a fresh clone needs only Docker, on amd64 or Apple Silicon.
- Unblocks #6, #7 and #8 with a working PHP 8.4 + MySQL + Mailpit environment.
- Follows the same layering as the sibling Tent project, so the setup is familiar.
