# docker-compose

Purpose: define the services of `docker-compose.yml`.

The file uses **YAML anchors** (e.g. `x-base: &base`, `x-app: &app`) to share image,
volumes, env files and `depends_on` between services, following the tent repository.

| Service | Image | Port (host) | Role |
| --- | --- | --- | --- |
| `base_build` | `dev_pidgey` | — | Builds the `dev_pidgey` image on a fresh clone; the other `dev_pidgey` services depend on its image. |
| `app` | `dev_pidgey` | 8080 → 80 | Apache + PHP serving the API, session endpoints and the built frontend at `/admin/`. Runs with `PHP_ENV=development`. |
| `frontend_dev` | `vite_pidgey` | `${FRONTEND_PORT:-8030}` → 8080 | Vite dev server (`yarn server`) with hot reload; proxies `/v1` to `app:80`; depends on `app`. See [frontend/development.md](../frontend/development.md). |
| `mysql` | `mysql:9` | 3306 | Database (development and test databases). Mounts `./dockerfiles/mysql/initdb.d` read-only at `/docker-entrypoint-initdb.d`. |
| `phpmyadmin` | `phpmyadmin` | 8040 | DB inspection during development. |
| `mailpit` | `axllent/mailpit` | 8025 (UI), 1025 (SMTP) | Fake SMTP server for development and integration tests. |
| `tests` | `dev_pidgey` | — | Runs `composer tests`; depends on `mysql` and `mailpit`. Runs with `PHP_ENV=test` and `DB_NAME=pidgey_test` (overriding `.env`'s `pidgey_development`). |

Notes:
- `app`, `tests` share the backend anchor; `frontend_dev` does not use it: it mounts `./frontend`
  as its working directory (see [volumes.md](volumes.md)). Frontend checks run in `frontend_dev`.
- Integration tests use a dedicated test database (`pidgey_test`) on the same `mysql`
  service; see [architecture/database.md](../architecture/database.md#test-database).
- The `mysql` image only grants `MYSQL_USER` on `MYSQL_DATABASE`. The init script
  `dockerfiles/mysql/initdb.d/01-grant-pidgey-dbs.sh` grants ``ALL ON `pidgey\_%`.*`` to
  `$MYSQL_USER`@`%` (read from the env, not hard-coded; `\_` escapes the `_` wildcard), so
  the app user can create and drop `pidgey_development`, `pidgey_test` and throwaway
  `pidgey_*_test` databases. Application containers never receive root credentials. Init
  scripts only run on an empty data volume (see [makefile.md](makefile.md#first-run)).
- Port numbers other than 8080, 8030 and 3306 are working assumptions.
- Published host ports are overridable via env vars, defaulting to the ports above (not set
  in `.env`): `APP_PORT` (8080), `MYSQL_PORT` (3306), `MAILPIT_UI_PORT` (8025),
  `MAILPIT_SMTP_PORT` (1025), `PHPMYADMIN_PORT` (8040), `FRONTEND_PORT` (8030). Set e.g. `MYSQL_PORT=3307` in your
  shell if a host port is already taken. Container ports are unchanged, so services still
  reach each other on `mysql:3306` / `mailpit:1025`.
