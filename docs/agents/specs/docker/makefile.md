# Makefile

Purpose: define the Makefile targets used for local work and CI.

| Target | Action |
| --- | --- |
| `build` | Build the `dev_pidgey` image. |
| `build-base` | Build the `dev_pidgey-base` image. |
| `push-base` | Push `dev_pidgey-base` to the registry. |
| `dev` | Open a shell in a `dev_pidgey` container with volumes mounted. |
| `dev-up` | `docker compose up` for app, frontend_dev, mysql, phpmyadmin, mailpit. |
| `tests` | Run all backend and frontend tests and linters in the `tests` container. |
| `ci-*` | Wrappers used by CircleCI (e.g. `ci-build`, `ci-tests`, `ci-lint`) that run the same commands non-interactively. |

`make tests` green is part of the definition of done (see [testing](../testing.md)).
