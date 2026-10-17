# docker-compose

Purpose: define the services of `docker-compose.yml`.

The file uses **YAML anchors** (e.g. `x-base: &base`, `x-app: &app`) to share image,
volumes, env files and `depends_on` between services, following the tent repository.

| Service | Image | Port (host) | Role |
| --- | --- | --- | --- |
| `app` | `dev_pidgey` | 8080 → 80 | Apache + PHP serving the API, session endpoints and static frontend. |
| `frontend_dev` | `dev_pidgey` | 8030 | Vite dev server (`yarn server`) with hot reload; proxies API calls to `app`. |
| `mysql` | `mysql:9` | 3306 | Database (development and test databases). |
| `phpmyadmin` | `phpmyadmin` | 8040 | DB inspection during development. |
| `mailpit` | `axllent/mailpit` | 8025 (UI), 1025 (SMTP) | Fake SMTP server for development and integration tests. |
| `tests` | `dev_pidgey` | — | Runs `composer tests` / frontend tests; depends on `mysql` and `mailpit`. |

Notes:
- `app`, `tests` share the backend anchor; `frontend_dev` uses the frontend working directory.
- Integration tests use a dedicated test database on the same `mysql` service.
- Port numbers other than 8080, 8030 and 3306 are working assumptions.
