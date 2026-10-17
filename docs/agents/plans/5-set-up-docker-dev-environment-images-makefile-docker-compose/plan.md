# Plan: Set up Docker dev environment (images, Makefile, docker-compose)

Issue: [5-set-up-docker-dev-environment-images-makefile-docker-compose.md](../../issues/5-set-up-docker-dev-environment-images-makefile-docker-compose.md)

## Overview
Create the Tent-style Docker development environment: the `dev_pidgey-base` and `dev_pidgey`
images, a `docker-compose.yml` with `app`/`tests`/`mysql`/`mailpit`/`phpmyadmin`, a Makefile,
a committed dev-only `.env`, a minimal `source/composer.json` and a throwaway `index.php`
stub. The specs and agent docs are aligned with the result. Nothing new runs on the host.

## Agents involved

- [backend](backend.md): `source/composer.json`, `source/public/index.php`, then `source/composer.lock`.
- [infra](infra.md): Dockerfiles, compose, Makefile, `.env`, `.gitignore`, `docker_volumes/`, end-to-end check.
- [product-owner](product-owner.md): `docs/agents/specs/docker/makefile.md`.

Execution order:
1. backend step 1.
2. infra steps 01–03.
3. backend step 2 (generate the lock inside the container).
4. infra step 04 (verify).
5. product-owner.
6. Coordinator docs (below).

Coordinator (architect) changes, done directly because they are root or agent-configuration docs:
- `.claude/agents/infra.md`: replace "development values live in `docker_volumes/configuration`
  or git-ignored `.env` files" with: a committed `.env` may hold only development-only
  placeholder values, and real secrets are never committed.
- `docs/agents/folder-structure.md`: drop *(planned)* from `source/`, `dockerfiles/`,
  `docker_volumes/` and `docker-compose.yml`, `Makefile`. Add a `.env` row (committed,
  development-only values).

## Shared contracts

- **Paths:** app root in the container is `/home/app/app` (= `./source`); the web root is
  `/home/app/app/public` (= `source/public/`); vendor is `/home/app/app/vendor`
  (= `./docker_volumes/vendor`, and `vendor/bin` is on `PATH`).
- **Container user:** `app`, uid 1000.
- **Composer files:** `composer.json` and `composer.lock` live in `source/`. Both Docker builds
  use `source/` as the build context and `COPY composer.*`, so they work before
  `composer.lock` exists.
- **Image tags:** `$(DOCKER_ID_USER)/dev_pidgey-base:$(BASE_VERSION)$(BASE_SUFFIX)` (registry
  `darthjee`, `BASE_SUFFIX` empty or `-arm64`); `dev_pidgey` built from it with
  `ARG BASE_VERSION` and `ARG BASE_SUFFIX=""`.
- **`.env` keys** (dev-only values): `MYSQL_ROOT_PASSWORD`,
  `MYSQL_DATABASE=pidgey_development`, `MYSQL_USER`, `MYSQL_PASSWORD`, `DB_HOST=mysql`,
  `DB_PORT=3306`, `DB_NAME=pidgey_development`, `DB_USER` (= `MYSQL_USER`), `DB_PASSWORD`
  (= `MYSQL_PASSWORD`), `SMTP_HOST=mailpit`, `SMTP_PORT=1025`.
- **Host ports:** app 8080→80, mysql 3306, mailpit 8025 (UI) / 1025 (SMTP), phpmyadmin 8040.
- **Stub response:** `GET /` → `200`, `Content-Type: application/json`, body `{"status":"ok"}`.
