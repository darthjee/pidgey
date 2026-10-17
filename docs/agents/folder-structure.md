# Folder Structure

The layout below is the target defined in
[specs/architecture/repository-layout.md](specs/architecture/repository-layout.md),
which is the source of truth. Entries marked *(planned)* do not exist yet.

## Project Root

| Directory / File | Description |
|-----------------|-------------|
| `source/` | PHP backend: `composer.json`, PSR-4 code under `lib/` (namespace `Pidgey\`), public web root with the front controller, SQL migrations and PHPUnit tests. |
| `frontend/` | *(planned)* React admin UI (Vite project); its built `dist/` is served by Apache as static files. |
| `dockerfiles/` | Docker image definitions: `dev_pidgey-base/`, `dev_pidgey/` (present); `circleci_pidgey-base/` (CI image) and `pidgey/` *(planned)*. |
| `docker_volumes/` | Host-side persistent data mounted into containers. Git-ignored except placeholders. |
| `dev/` | *(planned)* Development helpers: sample configuration, seed data, local-only scripts. |
| `scripts/` | *(planned)* Operational scripts used by Docker/CI (e.g. running migrations, waiting for MySQL). |
| `.circleci/` | *(planned)* CircleCI configuration. |
| `docker-compose.yml`, `Makefile` | Local orchestration and the `make` targets used locally and by CI. |
| `.env` | Committed, development-only values (local MySQL credentials, DB/SMTP hosts) loaded by the compose services. Never holds real secrets. |
| `docs/agents/` | Agent documentation: specs, issues, plans, architecture and guides. Entry point is `docs/agents/specs.md`. |
| `.github/` | PR template, commit message templates and Copilot instructions. |
| `AGENTS.md` | Agent entry point (rules and documentation index). |
| `CLAUDE.md` | Points Claude Code to `AGENTS.md`. |
| `README.md` | Project intro, first-run setup and local test/lint commands. |
| `LICENSE` | Project license. |

## source/

| Subdirectory | Description |
|--------------|-------------|
| `lib/` | PSR-4 root for namespace `Pidgey\`. |
| `public/` | Apache document root: `index.php` (currently a placeholder stub returning `{"status":"ok"}`, replaced by the front controller later); built frontend assets go under `static/`. |
| `migrations/` | *(planned)* Plain SQL migrations named `NNNN_description.sql`. |
| `tests/unit/` | *(planned)* PHPUnit unit suite (no database, no network). |
| `tests/integration/` | *(planned)* PHPUnit integration suite (against dockerized MySQL and Mailpit). |
| `tests/support/`, `tests/fixtures/` | *(planned)* Shared test helpers and static test data. |

## docker_volumes/

| Subdirectory | Description |
|--------------|-------------|
| `mysql_data/` | MySQL persistence. |
| `vendor/` | Composer vendor directory, kept out of the source tree. |
| `node_modules/` | *(planned)* Frontend Yarn dependencies. |
| `configuration/` | *(planned)* Local env/config files. |
| `cache/` | *(planned)* Composer/Yarn caches. |

## docs/agents/

| Subdirectory / File | Description |
|---------------------|-------------|
| `specs.md`, `specs/` | Specs hub and spec areas. The source of truth for the intended design. |
| `issues/` | Detailed issue files (`<issue_id>_<issue_name>.md`). |
| `plans/` | Implementation plans (`<issue_id>_<topic>/`). |
