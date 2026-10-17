# Makefile

Purpose: define the Makefile targets used for local work and CI.

| Target | Action |
| --- | --- |
| `build-base` | Build the `dev_pidgey-base` image. |
| `push-base` | Push `dev_pidgey-base` to the registry (manual; there is no automated image build). |
| `build` | Build the `dev_pidgey` image. |
| `dev` | Open a shell in the `tests` service (`docker compose run --rm tests bash`), with `mysql` and `mailpit` started. |
| `dev-up` | `docker compose up` for app, mysql, phpmyadmin, mailpit (and `frontend_dev`, future work). |
| `tests` | Run all backend and frontend tests and linters in the `tests` container. |
| `ci-*` | Wrappers used by CircleCI (e.g. `ci-build`, `ci-tests`, `ci-lint`) that run the same commands non-interactively. |

## Variables

| Variable | Meaning |
| --- | --- |
| `PROJECT` | Project name, `pidgey`; used to build image names (`dev_$(PROJECT)-base`, ...). |
| `BASE_VERSION` | Version tag of `dev_pidgey-base` to build, push and build from. |
| `DOCKER_ID_USER` | Docker Hub namespace for published images (`darthjee`). |
| `BASE_SUFFIX` | Architecture tag suffix: empty for amd64, `-arm64` for arm64. |

## First run

On a fresh clone:

```bash
export DOCKER_ID_USER=darthjee
make build-base && make build
docker compose run --rm tests composer install
make dev-up   # or: docker compose up app
```

Notes:

- **Apple Silicon:** set `BASE_SUFFIX=-arm64`, both for `make` and for `docker compose build`
  (e.g. `export BASE_SUFFIX=-arm64`).
- **Container user:** containers run as uid 1000; on a Linux host with a different uid, files
  created from a container may need a `chown`.
- **Changing `MYSQL_*` in `.env`:** MySQL only applies these on first initialization, so a
  reset is needed: stop `mysql`, empty `docker_volumes/mysql_data` (keep the placeholder
  file), then start it again.

`make tests` green is part of the definition of done (see [testing](../testing.md)).
