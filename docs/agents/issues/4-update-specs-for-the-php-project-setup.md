# Issue: Update specs for the PHP project setup

## Description

First step of the backend bootstrap Epic (#3). Before any setup code lands, update the specs
under `docs/agents/specs/` (and the agent docs that summarize them) so they match the decisions
made for the PHP project setup. Specs are the source of truth (see `AGENTS.md`), so the code in
the following sub-issues (#5–#8) must be able to follow them exactly.

The specs are **kept, not removed**: they still describe the target design. Replacing them with
as-built documentation is out of scope.

## Problem

The current specs either leave the setup decisions pending or contradict them:

- PHP version, registry and web root are still marked "Pending decision", and "PHP 8.x" is used
  throughout the docs.
- `docker/images.md` puts `gd` and Node.js/Yarn in the base image, says `dev_pidgey` is used for
  CI, and has no CI image.
- `deployment/pipeline.md` runs a single `backend_checks` job in `dev_pidgey` and an automated
  `build_images` job.
- The specs do not describe the compose sidecars, Makefile targets, migration/wait-for-db
  scripts or composer lint/test scripts that the following sub-issues implement.

## Expected Behavior

- No spec contradicts the decisions above; previously pending items resolved here are no
  longer marked "Pending decision".
- The specs hub `docs/agents/specs.md` still indexes every spec.
- Grep checks (outside `docs/agents/issues/`):
  - `grep -rn "8\.x" docs AGENTS.md` returns nothing.
  - `docker/images.md` no longer mentions `gd`.
  - The PHP version, web-root and registry items are no longer marked "Pending decision".

## Solution

- **`docker/images.md`**
  - Resolve the pending decision: **PHP 8.4** (`php:8.4-apache`, `cimg/php:8.4`).
  - `dev_pidgey-base` extensions for now: `zip`, `pdo`, `pdo_mysql`, `pcov`, Composer,
    `mod_rewrite`, non-root `app` user (uid 1000). Drop `gd` (Pidgey does no image
    processing; add it back only if a feature needs it). Node.js/Yarn are deferred to the frontend
    setup (keep the working assumption noted as deferred, not removed).
  - Multi-arch: tags `<version>` (amd64) and `<version>-arm64`, selected through
    `ARG BASE_SUFFIX` in the `dev_pidgey` Dockerfile. Built and pushed manually for now.
  - Add the CI image `circleci_pidgey-base` (`dockerfiles/circleci_pidgey-base/Dockerfile`):
    `cimg/php:8.4` + `zip`, `pdo_mysql`, `pcov`, Composer, project dependencies pre-installed at
    `/home/circleci/vendor`. Built and pushed manually.
  - Registry: Docker Hub `darthjee/`.
  - `dev_pidgey` is for local development and the compose services only (not CI); the CI image
    is `circleci_pidgey-base`.
  - Production image row (`pidgey`): pin to `php:8.4-apache` (the image itself stays out of scope).
- **`docker/compose.md`**: services `base_build` (builds the image on a fresh clone), `app`,
  `tests`, `mysql`, `mailpit`, `phpmyadmin`; YAML anchors; `tests` runs with `PHP_ENV=test`,
  `app` with `PHP_ENV=development`. `frontend_dev` stays in the spec as future work.
- **`docker/volumes.md`**: keep `source`, `mysql_data` and `vendor` as active; mark
  `frontend`, `node_modules`, `frontend/dist`, `configuration` and `cache` as future work
  (kept, not removed). Exact container paths are still fixed when the Dockerfiles are written.
- **`docker/makefile.md`**: targets `build-base`, `push-base`, `build`, `dev`, `tests`, `ci-*`;
  variables `PROJECT=pidgey`, `BASE_VERSION`, `DOCKER_ID_USER`; documented first-run
  (`make build-base && make build`, then `docker compose run --rm tests composer install`).
- **`deployment/pipeline.md`**: CI jobs run in `circleci_pidgey-base` (not `dev_pidgey`);
  backend split into `backend_checks` (`composer lint`) and `backend_tests` (MySQL + Mailpit
  service containers, wait-for-db, create test DB, migrate, `composer coverage`, Codacy
  `report` upload with `CODACY_PROJECT_TOKEN`). Frontend/release jobs and `build_images` are marked as
  future work (images are built and pushed manually for now).
- **`architecture/repository-layout.md`**: resolve the web-root decision to `source/public/`;
  note the stub `index.php` (JSON health response, no routing).
- **`architecture/database.md`**: the migrations script, a wait-for-db script, test-database
  creation, and the idempotency rules (re-run is a no-op; applied versions never reapplied;
  a failing migration stops the run and is not recorded; CI recreates the test DB each run).
- **`testing/backend.md`**: composer scripts `tests`, `tests:unit`, `tests:integration`,
  `coverage`, `lint`, `lint:fix`, `docs`, `complexity`; phpcs (PSR-12 + slevomat, PHPDoc
  required outside tests) and phpmd (unused-code gate + complexity report); test folders
  `unit/`, `integration/`, `support/`, `fixtures/`.
- **PHP version everywhere**: replace remaining "PHP 8.x" mentions with **8.4** in
  `architecture/backend.md`, `architecture/dependencies.md`, `docs/agents/contributing.md` and
  `docs/agents/architecture.md`.
- **CI image listing**: add `circleci_pidgey-base/` to the `dockerfiles/` rows in
  `architecture/repository-layout.md` and `docs/agents/folder-structure.md`.
- Update `docs/agents/folder-structure.md` / `architecture.md` summaries only where they
  contradict the updated specs.

### Out of scope

- Any code, Dockerfile or CI file (later sub-issues).
- Removing or rewriting specs as as-built documentation.
- `pipeline.md` pending decisions (production hosting and `dist/` upload, image tagging scheme,
  migrations on deploy) stay pending.

## Benefits

- Sub-issues #5–#8 implement against settled, non-contradictory specs instead of pending items.
- Agents following `AGENTS.md` ("respect pending decisions") are no longer blocked on decisions
  already made in Epic #3.
