# Repository Layout

Purpose: define the top-level folders of the repository and their responsibilities.

| Path | Content |
| --- | --- |
| `source/` | Backend (PHP). Contains `composer.json`, `lib/` (PSR-4 code), the public web root with the front controller, migrations and tests. |
| `frontend/` | React admin UI (Vite project). See [frontend/layout.md](../frontend/layout.md). |
| `dockerfiles/` | Docker image definitions: `dev_pidgey-base/`, `dev_pidgey/`, `circleci_pidgey-base/`, `pidgey/`. See [docker/images.md](../docker/images.md). |
| `docker_volumes/` | Host-side persistent data mounted into containers (mysql data, vendor, node_modules, configuration, cache). Git-ignored except placeholders. |
| `dev/` | Development helpers (sample configuration, seed data, local scripts not used in production). |
| `scripts/` | Operational scripts used by Docker/CI (e.g. running migrations, waiting for MySQL). |
| `docs/agents/` | Specs and agent documentation, starting at `docs/agents/specs.md`. |
| `docker-compose.yml`, `Makefile` | Local orchestration. See [docker](../docker.md). |
| `.circleci/` | CI configuration. See [deployment](../deployment.md). |
| `AGENTS.md` | Agent entry point. |
| `README.md` | Human-facing project intro, first-run setup and common commands. |

## Inside `source/`

| Path | Content |
| --- | --- |
| `source/composer.json` | Dependencies and composer scripts (see [testing](../testing.md)). |
| `source/lib/` | PSR-4 root for namespace `Pidgey\`. |
| `source/public/` | Apache document root (web root): `index.php` front controller; built frontend assets are placed under `static/` here. Initially a stub `index.php` returning a JSON health response (no routing yet). |
| `source/migrations/` | Plain SQL migration files. See [database.md](database.md). |
| `source/tests/unit/`, `source/tests/integration/` | PHPUnit suites. |
| `source/tests/support/`, `source/tests/fixtures/` | Shared test helpers and test fixture data. |
