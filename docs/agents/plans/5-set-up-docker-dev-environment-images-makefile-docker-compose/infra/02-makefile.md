# Makefile
Create the root `Makefile` with the targets this issue covers.

- Variables:
  - `PROJECT=pidgey`
  - `BASE_VERSION` (start at `0.0.1`)
  - `DOCKER_ID_USER ?= darthjee`
  - `BASE_SUFFIX ?=`
  - Derived image names: `BASE_IMAGE=$(DOCKER_ID_USER)/dev_$(PROJECT)-base`,
    `DEV_IMAGE=$(DOCKER_ID_USER)/dev_$(PROJECT)`.
- Targets (all `.PHONY`):
  - `build-base`: `docker build -f dockerfiles/dev_pidgey-base/Dockerfile -t
    $(BASE_IMAGE):$(BASE_VERSION)$(BASE_SUFFIX) source`.
  - `push-base`: `docker push $(BASE_IMAGE):$(BASE_VERSION)$(BASE_SUFFIX)`.
  - `build`: `docker build -f dockerfiles/dev_pidgey/Dockerfile --build-arg
    BASE_VERSION=$(BASE_VERSION) --build-arg BASE_SUFFIX=$(BASE_SUFFIX) -t $(DEV_IMAGE) source`.
  - `dev`: `docker compose run --rm tests bash`.
  - `dev-up`: `docker compose up app mysql phpmyadmin mailpit`.
- No `tests` target (#6) and no `ci-*` targets (#8).

## Files to Change
- `Makefile` — new.
