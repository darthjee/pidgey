# Update Docker specs
Bring the four Docker detail specs and their index in line with the Epic #3 decisions.

- **`docker/images.md`**
  - `dev_pidgey-base` row: `php:8.4-apache`; extensions `zip`, `pdo`, `pdo_mysql`, `pcov`;
    Composer; Apache `mod_rewrite`; non-root `app` user (uid 1000). Remove `gd` and the
    "Also includes Node.js + Yarn" sentence. Note multi-arch tags `<version>` (amd64) and
    `<version>-arm64`, built and pushed manually.
  - `dev_pidgey` row: `FROM dev_pidgey-base:<version><BASE_SUFFIX>` (`ARG BASE_SUFFIX`);
    "Used for local development and the compose services" (not CI).
  - New `circleci_pidgey-base` row: `dockerfiles/circleci_pidgey-base/Dockerfile`,
    `cimg/php:8.4` + `zip`, `pdo_mysql`, `pcov`, Composer, project dependencies pre-installed
    at `/home/circleci/vendor`; built and pushed manually; used by every CircleCI job.
  - `pidgey` row: `php:8.4-apache`; add "(future work — not part of the initial setup)".
  - Pending decisions: remove the PHP version and registry items (state "Registry: Docker Hub
    `darthjee/`" in the body). Keep the Node.js item, reworded: Node.js/Yarn are deferred to the
    frontend setup; working assumption remains the same base image.
- **`docker/compose.md`**: add `base_build` (builds `dev_pidgey` on a fresh clone; other
  services depend on its image); state `app` runs with `PHP_ENV=development` and `tests` with
  `PHP_ENV=test`; mark `frontend_dev` as future work. Do not change the `tests` row's
  frontend wording (out of scope).
- **`docker/volumes.md`**: keep `./source`, `mysql_data` and `vendor` as active rows; mark
  `./frontend`, `node_modules`, `configuration`, `cache` and `./frontend/dist` as future work.
- **`docker/makefile.md`**: targets `build-base`, `push-base`, `build`, `dev`, `tests`,
  `ci-*` (mark `dev-up`'s `frontend_dev` part as future work); add a Variables section
  (`PROJECT=pidgey`, `BASE_VERSION`, `DOCKER_ID_USER`, `BASE_SUFFIX`) and a "First run" section:
  `make build-base && make build`, then `docker compose run --rm tests composer install`.
- **`docker.md`** (index): update the images summary to "Image layering: base → dev → prod,
  plus the CI image".

## Files to Change
- `docs/agents/specs/docker/images.md` — PHP 8.4, extensions, CI image, multi-arch, pending items.
- `docs/agents/specs/docker/compose.md` — `base_build`, `PHP_ENV`, `frontend_dev` future.
- `docs/agents/specs/docker/volumes.md` — active vs. future rows.
- `docs/agents/specs/docker/makefile.md` — targets, variables, first run.
- `docs/agents/specs/docker.md` — index summary for images.
