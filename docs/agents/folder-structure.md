# Folder Structure

The repository is still mostly specs. The layout below is the target defined in
[specs/architecture/repository-layout.md](specs/architecture/repository-layout.md),
which is the source of truth. Entries marked *(planned)* do not exist yet.

## Project Root

| Directory / File | Description |
|-----------------|-------------|
| `source/` | *(planned)* PHP backend: `composer.json`, PSR-4 code under `lib/` (namespace `Pidgey\`), public web root with the front controller, SQL migrations and PHPUnit tests. |
| `frontend/` | *(planned)* React admin UI (Vite project); its built `dist/` is served by Apache as static files. |
| `dockerfiles/` | *(planned)* Docker image definitions: `dev_pidgey-base/`, `dev_pidgey/`, `circleci_pidgey-base/` (CI image), `pidgey/`. |
| `docker_volumes/` | *(planned)* Host-side persistent data mounted into containers. Git-ignored except placeholders. |
| `dev/` | *(planned)* Development helpers: sample configuration, seed data, local-only scripts. |
| `scripts/` | *(planned)* Operational scripts used by Docker/CI (e.g. running migrations, waiting for MySQL). |
| `.circleci/` | *(planned)* CircleCI configuration. |
| `docker-compose.yml`, `Makefile` | *(planned)* Local orchestration and the `make` targets used locally and by CI. |
| `docs/agents/` | Agent documentation: specs, issues, plans, architecture and guides. Entry point is `docs/agents/specs.md`. |
| `.github/` | PR template, commit message templates and Copilot instructions. |
| `AGENTS.md` | Agent entry point (rules and documentation index). |
| `CLAUDE.md` | Points Claude Code to `AGENTS.md`. |
| `LICENSE` | Project license. |

## source/ *(planned)*

| Subdirectory | Description |
|--------------|-------------|
| `lib/` | PSR-4 root for namespace `Pidgey\`. |
| `public/` | Apache document root: `index.php` front controller; built frontend assets go under `static/`. |
| `migrations/` | Plain SQL migrations named `NNNN_description.sql`. |
| `tests/unit/` | PHPUnit unit suite (no database, no network). |
| `tests/integration/` | PHPUnit integration suite (against dockerized MySQL and Mailpit). |

## docker_volumes/ *(planned)*

| Subdirectory | Description |
|--------------|-------------|
| `mysql_data/` | MySQL persistence. |
| `vendor/` | Composer vendor directory, kept out of the source tree. |
| `node_modules/` | Frontend Yarn dependencies. |
| `configuration/` | Local env/config files. |
| `cache/` | Composer/Yarn caches. |

## docs/agents/

| Subdirectory / File | Description |
|---------------------|-------------|
| `specs.md`, `specs/` | Specs hub and spec areas. The source of truth for the intended design. |
| `issues/` | Detailed issue files (`<issue_id>_<issue_name>.md`). |
| `plans/` | Implementation plans (`<issue_id>_<topic>/`). |
