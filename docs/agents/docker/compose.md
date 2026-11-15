# docker-compose

Purpose: define the services of `docker-compose.yml`.

The file uses **YAML anchors** (e.g. `x-base: &base`, `x-app: &app`) to share image,
volumes, env files and `depends_on` between services, following the tent repository.

| Service | Image | Port (host) | Role |
| --- | --- | --- | --- |
| `base_build` | `dev_pidgey` | — | Builds the `dev_pidgey` image on a fresh clone; the other `dev_pidgey` services depend on its image. |
| `app` | `dev_pidgey` | 8080 → 80 | Apache + PHP serving the API, auth endpoints and the built frontend at `/` (from `source/static/`, through the PHP static-file handler). Runs with `PHP_ENV=development`. |
| `frontend_dev` | `vite_pidgey` | `${FRONTEND_PORT:-8030}` → 8080 | Vite dev server (`yarn server`) with hot reload; proxies `/v1` and `*.json` to `app:80`; depends on `app`. See [frontend/development.md](../frontend/development.md). |
| `mysql` | `mysql:9` | 3306 | Database (development and test databases). Mounts `./dockerfiles/mysql/initdb.d` read-only at `/docker-entrypoint-initdb.d`. |
| `phpmyadmin` | `phpmyadmin` | 8040 | DB inspection during development. |
| `mailpit` | `axllent/mailpit:v1.32.0` | 8025 (UI), 1025 (SMTP) | Development SMTP server: plain, no auth, no TLS. Used by `app` and the manual checks only; tests never send to it or clear it. |
| `mailpit-plain` | `axllent/mailpit:v1.32.0` | — (container 2025 SMTP, 9025 API) | Test-only SMTP server: plain, no auth. |
| `mailpit-starttls` | `axllent/mailpit:v1.32.0` | — (container 2587 SMTP, 9587 API) | Test-only SMTP server: `MP_SMTP_REQUIRE_STARTTLS=true`, auth required. |
| `mailpit-ssl` | `axllent/mailpit:v1.32.0` | — (container 2465 SMTP, 9465 API) | Test-only SMTP server: `MP_SMTP_REQUIRE_TLS=true` (implicit TLS), auth required. |
| `tests` | `dev_pidgey` | — | Runs `composer tests`; depends on `mysql`, `mailpit-plain`, `mailpit-starttls` and `mailpit-ssl`. Runs with `PHP_ENV=test` and `DB_NAME=pidgey_test` (overriding `.env`'s `pidgey_development`). |

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
- Port numbers other than 8080, 8030, 3306 and the test Mailpit ports are working
  assumptions. The test Mailpit ports are part of the test contract: changing one means
  updating compose, CI and [email/testing.md](../email/testing.md) together.
- Published host ports are overridable via env vars, defaulting to the ports above (not set
  in `.env`): `APP_PORT` (8080), `MYSQL_PORT` (3306), `MAILPIT_UI_PORT` (8025),
  `MAILPIT_SMTP_PORT` (1025), `PHPMYADMIN_PORT` (8040), `FRONTEND_PORT` (8030). Set e.g. `MYSQL_PORT=3307` in your
  shell if a host port is already taken. Container ports are unchanged, so services still
  reach each other on `mysql:3306` / `mailpit:1025`. The test Mailpit instances publish no
  host port (see [below](#test-mailpit-instances)).

## Test Mailpit instances

The behavior and the paths each instance covers are in
[email/testing.md](../email/testing.md#mailpit-instances).

- **Pinned tag:** every Mailpit image (`mailpit` and the three test instances) uses the same
  exact tag, `v1.32.0`; never an untagged or `latest` image. The CI base image copies the
  binary from the same tag (see [images.md](images.md)); bump both together.
- **Ports:** each test instance listens on its own SMTP and API ports inside its container
  (`MP_SMTP_BIND_ADDR` / `MP_UI_BIND_ADDR`), identical to the CI instances, which all run
  on `localhost`. The test instances' ports are **not published**
  to the host: only `tests` reaches them.
- **Auth:** `mailpit-starttls` and `mailpit-ssl` take the test-only auth user and password
  inline through `MP_SMTP_AUTH`.
- **TLS:** `source/tests/fixtures/tls/` is mounted **read-only** at `/fixtures/tls` into
  `mailpit-starttls` and `mailpit-ssl`, which use the leaf certificate and key whose SAN
  covers their service names: `MP_SMTP_TLS_CERT=/fixtures/tls/server.pem` and
  `MP_SMTP_TLS_KEY=/fixtures/tls/server-key.pem`. The fixtures are regenerated with
  `source/tests/fixtures/tls/generate.sh`.
- `make dev-up` does not start the test instances; they start as dependencies of `tests`.

## Environment

- **Development `.env`:** sets `SMTP_ALLOW_PRIVATE_HOSTS=true`, so `app` (and `make dev`)
  can reach the dev `mailpit`.
- **`tests` service:** in addition to `.env`, sets:
  - `TEST_MAILPIT_PLAIN=mailpit-plain:2025:9025`;
  - `TEST_MAILPIT_STARTTLS=mailpit-starttls:2587:9587`;
  - `TEST_MAILPIT_SSL=mailpit-ssl:2465:9465`;
  - `SMTP_ALLOW_PRIVATE_HOSTS=true`;
  - `SMTP_CA_FILE=tests/fixtures/tls/ca.pem`;
  - `SMTP_ENCRYPTION_KEY` (the development key, from `.env`).

  Short SMTP timeouts for the fake-server timeout tests are set per test, not here (see
  [architecture/configuration.md](../architecture/configuration.md#smtp-transport)).
