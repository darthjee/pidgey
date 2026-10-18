PROJECT=pidgey
BASE_VERSION=0.0.1
DOCKER_ID_USER?=darthjee
BASE_SUFFIX?=$(if $(filter arm64 aarch64,$(shell uname -m)),-arm64,)

BASE_IMAGE=$(DOCKER_ID_USER)/dev_$(PROJECT)-base
DEV_IMAGE=$(DOCKER_ID_USER)/dev_$(PROJECT)

CI_BASE_VERSION=0.0.1
CI_BASE_IMAGE=$(DOCKER_ID_USER)/circleci_$(PROJECT)-base

.PHONY: build-base push-base build-ci-base push-ci-base build dev dev-up tests migrate db-reset

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

dev:
	docker compose run --rm tests bash

dev-up:
	docker compose up app mysql phpmyadmin mailpit

tests:
	docker compose run --rm tests sh -c "composer lint && composer tests"

migrate:
	docker compose run --rm app composer db:prepare

db-reset:
	docker compose run --rm app sh -c "composer db:drop && composer db:prepare"
