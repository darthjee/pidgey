# Issue: Set up CircleCI with lint, tests and Codacy coverage

## Description

This is the last part of the backend bootstrap Epic (#3). Set up CircleCI (2.1) so that every branch and tag runs the backend lint and test suites, with coverage uploaded to Codacy. It follows `docs/agents/specs/deployment/pipeline.md` and the CI of the Tent repository (`darthjee/tent`).

Depends on the lint/tests sub-issue (#6) and the DB infrastructure sub-issue, because the test job runs wait-for-db, creates the test DB and migrates.

## Problem

There is no CI. Lint violations, failing tests and coverage regressions are only caught when someone runs `make tests` locally, and coverage is not tracked anywhere.

## Expected Behavior

- A push to any branch or tag runs `backend_checks` and `backend_tests` on CircleCI, and both are green on `main`.
- A lint violation fails `backend_checks`, and a failing test fails `backend_tests`.
- Coverage for the commit appears on Codacy.

## Solution

### CI image: `dockerfiles/circleci_pidgey-base/Dockerfile`

- Based on `cimg/php:8.4`. As root, install `unzip git curl libzip-dev $PHPIZE_DEPS`, the extensions `zip`, `pdo_mysql` and `pcov`, and Composer. Then switch back to `circleci`.
- Put `vendor/bin` on `PATH`. Copy `source/composer.*` and pre-install the dependencies into `/home/circleci/vendor`.
- The image is amd64 only. It is built and pushed manually as `darthjee/circleci_pidgey-base:<version>`.

### `.circleci/config.yml`

- Commands, using the Tent pattern:
  - `prepare_source`: check out, then move the contents of `source/` to the project root.
  - `composer_setup`: copy the pre-baked `/home/circleci/vendor`, then run `composer install`.
- Jobs call composer directly, with no Makefile wrappers.
  - `backend_checks`: `composer lint`.
  - `backend_tests`:
    - The primary container is `circleci_pidgey-base`. The service containers are `mysql:9` (the same image as docker-compose) and `axllent/mailpit`.
    - Env vars on the primary container: `DB_HOST`, `DB_PORT`, `DB_NAME=pidgey_test`, `SMTP_HOST=mailpit`, `SMTP_PORT=1025`, and the DB credentials. The MySQL container sets `MYSQL_ROOT_PASSWORD`.
    - The DB user is **root** (`DB_USER=root`, `DB_PASSWORD` = the root password). Service containers cannot mount the `initdb.d` grant script, and the tests must be able to create `pidgey_test` and the throwaway `pidgey_runner_*_test` databases.
    - Steps: `composer db:wait` → `composer db:create` → `composer db:migrate` (or `composer db:prepare`) → `composer coverage` → Codacy upload with `bash <(curl -Ls https://coverage.codacy.com/get.sh) report -r coverage/clover.xml`. There is no `--partial`/`final`, because only one job uploads coverage.
- Workflow: both jobs run in parallel on all branches **and tags** (`filters: tags: only: /.*/`).

### Codacy

- Add an analysis exclusions config at the repo root (`.codacy.yml`). It excludes, for example, vendor, coverage, phpdoc and docs.
- `CODACY_PROJECT_TOKEN` is set only in the CircleCI project settings.
- The Clover paths are relative to the flattened root (`lib/...`), while the repo paths are `source/lib/...`. Check that Codacy matches them, as it does for Tent.

### Makefile

- Add the `build-ci-base` and `push-ci-base` targets for `circleci_pidgey-base`, built from `source` as the context. There are no `ci-*` wrappers.

### Specs and docs

- `docs/agents/specs/docker/makefile.md`: remove the `ci-*` row and add `build-ci-base`/`push-ci-base`.
- `docs/agents/specs/deployment/pipeline.md`: say that checks run on every branch and tag.
- Document the CircleCI/Codacy setup steps: enable the project on CircleCI and Codacy, set `CODACY_PROJECT_TOKEN`, and build/push the CI image.

### Out of scope

- Frontend jobs, image build/release jobs, `upload_fe_files` and the production release.

## Benefits

- Every push is gated by lint and the full unit and integration suites.
- Coverage is tracked on Codacy from the start of the project.
