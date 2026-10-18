# Plan: Set up CircleCI with lint, tests and Codacy coverage

Issue: [8-set-up-circleci-with-lint-tests-and-codacy-coverage.md](../../issues/8-set-up-circleci-with-lint-tests-and-codacy-coverage.md)

## Overview

Add CircleCI (2.1) for the backend: a manually pushed `circleci_pidgey-base` CI image, a
`.circleci/config.yml` with `backend_checks` (`composer lint`) and `backend_tests`
(MySQL 9 + Mailpit service containers, DB prepare, `composer coverage`, single Codacy upload),
running in parallel on every branch and tag, plus a root `.codacy.yml`. Specs are updated
first (product-owner), then the implementation follows them (infra).

## Agents involved

- [product-owner](product-owner.md)
- [infra](infra.md)

## Shared contracts

- **CI image**: `darthjee/circleci_pidgey-base:0.0.1`, Dockerfile
  `dockerfiles/circleci_pidgey-base/Dockerfile`, build context `source/`, amd64 only (no
  `-arm64` tag). Dependencies pre-installed at `/home/circleci/vendor`.
- **Makefile targets**: `build-ci-base`, `push-ci-base` (variables `CI_BASE_VERSION=0.0.1`,
  `CI_BASE_IMAGE=$(DOCKER_ID_USER)/circleci_$(PROJECT)-base`). No `ci-*` wrappers; CI calls
  composer directly.
- **Jobs**: `backend_checks` → `composer lint`; `backend_tests` → `composer db:prepare` →
  `composer coverage` → `bash <(curl -Ls https://coverage.codacy.com/get.sh) report -r coverage/clover.xml`
  (no `--partial`/`final`).
- **CI DB access**: service image `mysql:9` named `mysql` with `MYSQL_ROOT_PASSWORD=root`;
  the primary container uses `DB_HOST=mysql`, `DB_PORT=3306`, `DB_NAME=pidgey_test`,
  `DB_USER=root`, `DB_PASSWORD=root`, `SMTP_HOST=mailpit`, `SMTP_PORT=1025`, `PHP_ENV=test`.
  Root is used because service containers cannot run the `initdb.d` grant script and tests
  create `pidgey_test` and `pidgey_runner_*_test` databases.
- **Triggers**: all branches and all tags (`filters: tags: only: /.*/` on every job).
- **Secrets**: `CODACY_PROJECT_TOKEN`, set only in CircleCI project settings.
- **Codacy config**: `.codacy.yml` at the repo root with `exclude_paths`.
