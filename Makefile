PROJECT=pidgey
BASE_VERSION=0.0.1
DOCKER_ID_USER?=darthjee
BASE_SUFFIX?=$(if $(filter arm64 aarch64,$(shell uname -m)),-arm64,)

BASE_IMAGE=$(DOCKER_ID_USER)/dev_$(PROJECT)-base
DEV_IMAGE=$(DOCKER_ID_USER)/dev_$(PROJECT)

CI_BASE_VERSION=0.0.1
CI_BASE_IMAGE=$(DOCKER_ID_USER)/circleci_$(PROJECT)-base

FE_BASE_VERSION=0.0.1
FE_BASE_IMAGE=$(DOCKER_ID_USER)/vite_$(PROJECT)-base
FE_IMAGE=$(DOCKER_ID_USER)/vite_$(PROJECT)
FE_RELEASE_DIR?=source/public/admin

.PHONY: build-base push-base build-ci-base push-ci-base build dev dev-up tests migrate db-reset \
	build-fe-base push-fe-base build-fe fe-install build-fe-release

build-base:
	docker build -f dockerfiles/dev_$(PROJECT)-base/Dockerfile -t $(BASE_IMAGE):$(BASE_VERSION)$(BASE_SUFFIX) source

push-base:
	docker push $(BASE_IMAGE):$(BASE_VERSION)$(BASE_SUFFIX)

build-ci-base:
	docker build --platform linux/amd64 -f dockerfiles/circleci_$(PROJECT)-base/Dockerfile -t $(CI_BASE_IMAGE):$(CI_BASE_VERSION) source

push-ci-base:
	docker push $(CI_BASE_IMAGE):$(CI_BASE_VERSION)

build:
	docker build -f dockerfiles/dev_$(PROJECT)/Dockerfile --build-arg BASE_VERSION=$(BASE_VERSION) --build-arg BASE_SUFFIX=$(BASE_SUFFIX) -t $(DEV_IMAGE) source

build-fe-base:
	docker build -f dockerfiles/vite_$(PROJECT)-base/Dockerfile -t $(FE_BASE_IMAGE):$(FE_BASE_VERSION)$(BASE_SUFFIX) .

push-fe-base:
	docker push $(FE_BASE_IMAGE):$(FE_BASE_VERSION)$(BASE_SUFFIX)

build-fe:
	docker build -f dockerfiles/vite_$(PROJECT)/Dockerfile --build-arg BASE_VERSION=$(FE_BASE_VERSION) --build-arg BASE_SUFFIX=$(BASE_SUFFIX) -t $(FE_IMAGE) .

fe-install:
	docker compose run --rm --no-deps frontend_dev yarn install

build-fe-release:
	$(if $(strip $(FE_RELEASE_DIR)),,$(error FE_RELEASE_DIR is empty))
	docker compose run --rm --no-deps frontend_dev yarn build
	rm -rf $(FE_RELEASE_DIR)
	mkdir -p $(FE_RELEASE_DIR)
	cp -R frontend/dist/. $(FE_RELEASE_DIR)/

dev:
	docker compose run --rm tests bash

dev-up:
	docker compose up app mysql phpmyadmin mailpit frontend_dev

tests:
	docker compose run --rm tests sh -c "composer lint && composer tests"
	docker compose run --rm --no-deps frontend_dev sh -c "yarn lint && yarn coverage"

migrate:
	docker compose run --rm app composer db:prepare

db-reset:
	docker compose run --rm app sh -c "composer db:drop && composer db:prepare"
