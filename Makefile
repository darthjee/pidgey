PROJECT=pidgey
BASE_VERSION=0.0.1
DOCKER_ID_USER?=darthjee
BASE_SUFFIX?=

BASE_IMAGE=$(DOCKER_ID_USER)/dev_$(PROJECT)-base
DEV_IMAGE=$(DOCKER_ID_USER)/dev_$(PROJECT)

.PHONY: build-base push-base build dev dev-up

build-base:
	docker build -f dockerfiles/dev_$(PROJECT)-base/Dockerfile -t $(BASE_IMAGE):$(BASE_VERSION)$(BASE_SUFFIX) source

push-base:
	docker push $(BASE_IMAGE):$(BASE_VERSION)$(BASE_SUFFIX)

build:
	docker build -f dockerfiles/dev_$(PROJECT)/Dockerfile --build-arg BASE_VERSION=$(BASE_VERSION) --build-arg BASE_SUFFIX=$(BASE_SUFFIX) -t $(DEV_IMAGE) source

dev:
	docker compose run --rm tests bash

dev-up:
	docker compose up app mysql phpmyadmin mailpit
