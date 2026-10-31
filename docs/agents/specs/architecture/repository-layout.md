# Repository Layout

Purpose: define the top-level folders of the repository and their responsibilities.

| Path | Content |
| --- | --- |
| `source/` | Backend (PHP). Contains `composer.json`, `lib/` (PSR-4 code), the public web root with the front controller, migrations and tests. |
| `frontend/` | React admin UI (Vite project). See [frontend/layout.md](../frontend/layout.md). |
| `dockerfiles/` | Docker image definitions: `dev_pidgey-base/`, `dev_pidgey/`, `circleci_pidgey-base/`, `vite_pidgey-base/`, `vite_pidgey/`, and a frontend deploy image if one is added (see [docker/images.md](../docker/images.md#deploy-tooling)). See [docker/images.md](../docker/images.md). |
| `docker_volumes/` | Host-side persistent data mounted into containers (mysql data, vendor, node_modules, configuration, cache). Git-ignored except placeholders. |
| `dev/` | Development helpers (sample configuration, seed data, local scripts not used in production). |
| `scripts/` | Host-side / CI-only operational scripts (DB scripts live in `source/bin/`, since only `source/` ships in a release). Not created until such a script is needed. |
| `docs/agents/` | Specs and agent documentation, starting at `docs/agents/specs.md`. |
| `docker-compose.yml`, `Makefile` | Local orchestration. See [docker](../docker.md). |
| `.circleci/` | CI configuration (owned by infra), including `.circleci/backend-exclude.txt`, the list of paths the backend upload leaves out of a release (see [deployment/release.md](../deployment/release.md#backend-upload-upload_backend)). See [deployment](../deployment.md). |
| `.codacy.yml` | Codacy static-analysis exclusions (`exclude_paths`). See [pipeline](../deployment/pipeline.md#codacy). |
| `AGENTS.md` | Agent entry point. |
| `README.md` | Human-facing project intro, first-run setup and common commands. |

## Inside `source/`

| Path | Content |
| --- | --- |
| `source/composer.json` | Dependencies and composer scripts (see [testing](../testing.md)). |
| `source/lib/` | PSR-4 root for namespace `Pidgey\`. |
| `source/bin/` | Executable PHP CLI entry points (`wait-for-db`, `create-db`, `drop-db`, `migrate`), backed by `Pidgey\Infra`. See [database.md](database.md#database-scripts). |
| `source/public/` | Apache document root (web root): `index.php` front controller; the built frontend is released under `admin/` here and served at `/admin/` (see [frontend/build.md](../frontend/build.md)). Initially a stub `index.php` returning a JSON health response (no routing yet). The front controller is specified in [request-handler/front-controller.md](../request-handler/front-controller.md). |
| `source/config/` | Runtime configuration. In production it holds `config/.env`, a symlink to the shared env file on the host (see [configuration.md](configuration.md#env-file) and [deployment/server.md](../deployment/server.md#the-configenv-link)). `config/.env` is git-ignored. |
| `source/migrations/` | Plain SQL migration files. See [database.md](database.md). |
| `source/tests/unit/`, `source/tests/integration/` | PHPUnit suites. |
| `source/tests/support/`, `source/tests/fixtures/` | Shared test helpers and test fixture data (e.g. migration fixture sets in `source/tests/fixtures/migrations/`). |
