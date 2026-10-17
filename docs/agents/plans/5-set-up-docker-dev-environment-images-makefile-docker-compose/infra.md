# Infra Plan: Set up Docker dev environment (images, Makefile, docker-compose)

Main plan: [plan.md](plan.md)

## Shared contracts

- App root `/home/app/app` (= `./source`), web root `/home/app/app/public`, vendor
  `/home/app/app/vendor` (= `./docker_volumes/vendor`, `vendor/bin` on `PATH`), user `app`
  uid 1000.
- Build context `source/`, `COPY composer.*` (works before `composer.lock` exists).
- Tags `$(DOCKER_ID_USER)/dev_pidgey-base:$(BASE_VERSION)$(BASE_SUFFIX)`; `dev_pidgey` takes
  `ARG BASE_VERSION`, `ARG BASE_SUFFIX=""`.
- `.env` keys and host ports as listed in [plan.md](plan.md#shared-contracts).

## Steps

- [01 — Base and dev Dockerfiles](infra/01-dockerfiles.md)
- [02 — Makefile](infra/02-makefile.md)
- [03 — Compose, .env, volumes and .gitignore](infra/03-compose-env-volumes.md)
- [04 — End-to-end verification](infra/04-verify.md)

## Notes
- Reference: Tent `dockerfiles/`, `docker-compose.yml` and `Makefile` as summarised in the
  issue. Leave out `gd`/`exif`, upload `.ini`, Node and Tent's reverse proxy.
- No CI config exists yet (#8), so there is no `## CI Checks` section.
