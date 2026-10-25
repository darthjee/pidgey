# Makefile

Purpose: define the Makefile targets used for local work and CI.

| Target | Action |
| --- | --- |
| `build-base` | Build the `dev_pidgey-base` image. |
| `push-base` | Push `dev_pidgey-base` to the registry (manual; there is no automated image build). |
| `build` | Build the `dev_pidgey` image. |
| `dev` | Open a shell in the `tests` service (`docker compose run --rm tests bash`), with `mysql` and `mailpit` started. |
| `dev-up` | `docker compose up` for app, mysql, phpmyadmin, mailpit and `frontend_dev`. |
| `migrate` | Prepare the development database: `docker compose run --rm app composer db:prepare` (wait, create if missing, migrate). Runs on `app` so it targets `pidgey_development` even though `make dev`'s shell points at `pidgey_test`. |
| `db-reset` | Drop and re-prepare the development database: `docker compose run --rm app sh -c "composer db:drop && composer db:prepare"`. |
| `tests` | Run tests and linters: backend in the `tests` container (`docker compose run --rm tests sh -c "composer lint && composer tests"`), then frontend in `frontend_dev` (`docker compose run --rm frontend_dev sh -c "yarn lint && yarn coverage"`). |
| `build-ci-base` | Build the `circleci_pidgey-base` CI image (amd64 only) from the `source/` context, tagged `$(CI_BASE_IMAGE):$(CI_BASE_VERSION)`. |
| `push-ci-base` | Push `circleci_pidgey-base` to the registry (manual; there is no automated image build). |
| `build-fe-base` | Build the `vite_pidgey-base` image from the repository-root context, tagged `$(FE_BASE_IMAGE):$(FE_BASE_VERSION)$(BASE_SUFFIX)`. |
| `push-fe-base` | Push `vite_pidgey-base` to the registry (manual; there is no automated image build). |
| `build-fe` | Build the `vite_pidgey` image on top of `vite_pidgey-base`. |
| `fe-install` | Install frontend dependencies into `docker_volumes/node_modules`: `docker compose run --rm frontend_dev yarn install`. |
| `build-fe-release` | Run `yarn build` in `frontend_dev`, then replace `$(FE_RELEASE_DIR)` with the contents of `frontend/dist/` (see [frontend/build.md](../frontend/build.md)). |

There are no `ci-*` wrapper targets: CircleCI calls composer directly inside the CI image
(see [pipeline](../deployment/pipeline.md)).

## Variables

| Variable | Meaning |
| --- | --- |
| `PROJECT` | Project name, `pidgey`; used to build image names (`dev_$(PROJECT)-base`, ...). |
| `BASE_VERSION` | Version tag of `dev_pidgey-base` to build, push and build from. |
| `DOCKER_ID_USER` | Docker Hub namespace for published images (`darthjee`). |
| `CI_BASE_VERSION` | Version tag of `circleci_pidgey-base` to build and push (`0.0.1`). |
| `CI_BASE_IMAGE` | CI image name, `$(DOCKER_ID_USER)/circleci_$(PROJECT)-base`. |
| `FE_BASE_VERSION` | Version tag of `vite_pidgey-base` to build, push and build from (`0.0.1`). |
| `FE_BASE_IMAGE` | Frontend base image name, `$(DOCKER_ID_USER)/vite_$(PROJECT)-base`. |
| `FE_IMAGE` | Frontend image name, `$(DOCKER_ID_USER)/vite_$(PROJECT)`. |
| `FE_RELEASE_DIR` | Release folder for the built frontend, `source/public/admin`. |
| `BASE_SUFFIX` | Architecture tag suffix: empty for amd64, `-arm64` for arm64. Defaults to `-arm64` when `uname -m` is `arm64`/`aarch64`, empty otherwise; an explicit `BASE_SUFFIX=` overrides. |

## First run

On a fresh clone:

```bash
export DOCKER_ID_USER=darthjee
make build-base && make build
make build-fe-base && make build-fe
docker compose run --rm tests composer install
make fe-install
make dev-up   # or: docker compose up app
```

The API is then at `http://localhost:8080` and the frontend dev server at
`http://localhost:8030/admin/` (see [frontend/development.md](../frontend/development.md#first-run)).

Notes:

- **Apple Silicon:** `make` detects arm64 and sets `BASE_SUFFIX=-arm64` automatically. Docker
  Compose does not auto-detect, so `docker compose build` still needs
  `export BASE_SUFFIX=-arm64`.
- **Container user:** containers run as uid 1000; on a Linux host with a different uid, files
  created from a container may need a `chown`.
- **Changing `MYSQL_*` in `.env`:** MySQL only applies these on first initialization, so a
  reset is needed: stop `mysql`, empty `docker_volumes/mysql_data` (keep the placeholder
  file), then start it again.
- **Existing `mysql` volume and the `pidgey\_%` grant:** the grant init script (see
  [compose.md](compose.md)) only runs on an empty volume, so a volume created before it
  needs a one-time fix: either reset the volume as above (recommended; no data is lost at
  this stage), or run the same `GRANT` manually as root against the running `mysql` service.
- **Development database from the dev shell:** `make dev` uses the `tests` service
  (`DB_NAME=pidgey_test`); to migrate the development database from there, run
  `DB_NAME=pidgey_development composer db:migrate`.

`make tests` green is part of the definition of done (see [testing](../testing.md)).
