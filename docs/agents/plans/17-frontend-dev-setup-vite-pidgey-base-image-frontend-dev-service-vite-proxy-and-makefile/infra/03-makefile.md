# Makefile targets

Add the frontend variables and targets, following the existing style.

- Variables, after the CI block:
  ```make
  FE_BASE_VERSION=0.0.1
  FE_BASE_IMAGE=$(DOCKER_ID_USER)/vite_$(PROJECT)-base
  FE_IMAGE=$(DOCKER_ID_USER)/vite_$(PROJECT)
  FE_RELEASE_DIR?=source/public/admin
  ```
- Targets:
  ```make
  build-fe-base:
  	docker build -f dockerfiles/vite_$(PROJECT)-base/Dockerfile -t $(FE_BASE_IMAGE):$(FE_BASE_VERSION)$(BASE_SUFFIX) .

  push-fe-base:
  	docker push $(FE_BASE_IMAGE):$(FE_BASE_VERSION)$(BASE_SUFFIX)

  build-fe:
  	docker build -f dockerfiles/vite_$(PROJECT)/Dockerfile --build-arg BASE_VERSION=$(FE_BASE_VERSION) --build-arg BASE_SUFFIX=$(BASE_SUFFIX) -t $(FE_IMAGE) .

  fe-install:
  	docker compose run --rm --no-deps frontend_dev yarn install

  build-fe-release:
  	docker compose run --rm --no-deps frontend_dev yarn build
  	rm -rf $(FE_RELEASE_DIR)
  	mkdir -p $(FE_RELEASE_DIR)
  	cp -R frontend/dist/. $(FE_RELEASE_DIR)/
  ```
- `dev-up`: `docker compose up app mysql phpmyadmin mailpit frontend_dev`.
- `tests`: keep the backend line, then add `docker compose run --rm --no-deps frontend_dev sh -c "yarn lint && yarn coverage"`.
- Add `build-fe-base push-fe-base build-fe fe-install build-fe-release` to `.PHONY`.

Guard `FE_RELEASE_DIR` against being empty (for example `$(if $(FE_RELEASE_DIR),,$(error FE_RELEASE_DIR is empty))`), so `rm -rf` never runs on an empty path.

## Files to Change

- `Makefile` — `FE_*` variables, five new targets, `dev-up`, `tests`, `.PHONY`.
