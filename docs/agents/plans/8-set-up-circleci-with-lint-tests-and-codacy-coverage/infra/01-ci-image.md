# CI image Dockerfile and Makefile targets

Create `dockerfiles/circleci_pidgey-base/Dockerfile`, following Tent's
`circleci_tent-base`:
- `FROM cimg/php:8.4`.
- `USER root`: `apt-get install -y --no-install-recommends unzip git curl libzip-dev $PHPIZE_DEPS`
  (skip anything cimg already ships), `docker-php-ext-install zip pdo_mysql` (or `pecl`/the
  cimg-provided mechanism if `docker-php-ext-*` is unavailable in cimg), `pecl install pcov`
  and enable it, ensure Composer is present, clean apt lists.
- `USER circleci`; `ENV PATH="$PATH:/home/circleci/project/vendor/bin"`.
- `WORKDIR /home/circleci/vendor-build` (or similar): `COPY --chown=circleci:circleci composer.* ./`,
  `composer install --no-interaction --no-progress`, then move the resulting `vendor/` to
  `/home/circleci/vendor`.
- Verify locally: `docker run --rm <image> php -m` lists `pdo_mysql`, `zip`, `pcov`.

Add to the `Makefile`: `CI_BASE_VERSION=0.0.1`, `CI_BASE_IMAGE=$(DOCKER_ID_USER)/circleci_$(PROJECT)-base`,
targets `build-ci-base` (`docker build -f dockerfiles/circleci_$(PROJECT)-base/Dockerfile -t $(CI_BASE_IMAGE):$(CI_BASE_VERSION) source`)
and `push-ci-base`; add both to `.PHONY`. No arch suffix (amd64 only; on Apple Silicon build
with `--platform linux/amd64`).

## Files to Change
- `dockerfiles/circleci_pidgey-base/Dockerfile` — new CI image.
- `Makefile` — `build-ci-base`/`push-ci-base` targets and variables.
