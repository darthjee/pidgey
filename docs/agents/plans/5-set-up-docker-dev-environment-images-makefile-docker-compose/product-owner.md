# Product Owner Plan: Set up Docker dev environment (images, Makefile, docker-compose)

Main plan: [plan.md](plan.md)

## Implementation Steps

### Step 1 — Align makefile.md with the implemented setup
In `docs/agents/specs/docker/makefile.md`:
- `dev` row: "Open a shell in the `tests` service (`docker compose run --rm tests bash`),
  with `mysql` and `mailpit` started."
- First run: `export DOCKER_ID_USER=darthjee`, `make build-base && make build`,
  `docker compose run --rm tests composer install`, then `make dev-up` (or
  `docker compose up app`).
- Under First run, add short notes:
  - Apple Silicon: `BASE_SUFFIX=-arm64`, through both make and `docker compose build`.
  - Containers run as uid 1000: on a Linux host with another uid, files may need a `chown`.
  - Changing `MYSQL_*` in `.env` needs a reset: stop `mysql`, empty
    `docker_volumes/mysql_data` (keep the placeholder), then start it again.

## Files to Change
- `docs/agents/specs/docker/makefile.md` — `dev` row, first-run steps, notes.

## Notes
- The `tests` and `ci-*` rows stay as they are: they are the target design, implemented in
  #6 and #8.
