# Architecture

Index of the architecture docs: repository layout, backend structure, database access,
dependency policy and configuration. The sections below summarize the architecture; the
detail files hold the full contract.

## Detail files

| File | Content |
| --- | --- |
| [repository-layout.md](architecture/repository-layout.md) | Top-level folders and what goes in each. |
| [backend.md](architecture/backend.md) | Plain PHP backend: namespace, front controller, layers. |
| [database.md](architecture/database.md) | MySQL 9 via raw PDO; migrations strategy. |
| [dependencies.md](architecture/dependencies.md) | Runtime vs dev dependency policy. |
| [configuration.md](architecture/configuration.md) | Every environment variable, its default and effect. |

## Overview

Pidgey is a single PHP application served by Apache, backed by MySQL 9:

- **Backend:** plain PHP 8.4 with **no application framework**. A single front controller
  (`source/public/index.php`) handles every dynamic request: the JSON API (Bearer-token
  authenticated) and the auth and admin endpoints (JWT `access_token` cookie).
- **Frontend:** a React 19 admin UI built with Vite. Its `dist/` output is released into
  `source/static` (`make build-fe-release`) and served at `/` by the PHP static-file handler
  (`StaticFileHandler`).
- **Database:** MySQL 9 accessed only through raw PDO with prepared statements (no ORM),
  using forward-only plain SQL migrations.
- **Email:** synchronous delivery. Each send request opens a connection to the
  organization's active SMTP server and returns the outcome in the HTTP response.
  There is no queue and no retry.

See [overview/components.md](overview/components.md) for the component map.

## Source Code Layout

All backend source code lives under `source/`. Frontend code lives under `frontend/`.

### `source/lib/` — namespace `Pidgey\`

Layers, with dependencies flowing downward only (HTTP → Services → Repositories / SMTP):

| Namespace | Responsibility |
| --- | --- |
| `Pidgey\Http\` | Request/Response, router, request handlers, middlewares (cookie-session gate, Bearer-token auth), JSON encoding. No SQL, no SMTP. See [request-handler.md](request-handler.md). |
| `Pidgey\Service\` | Business logic (registration, login, token issuing/revocation, sending a message). Orchestrates repositories and transport. |
| `Pidgey\Repository\` | All SQL, through PDO prepared statements. Every organization-scoped query filters by `organization_id`. |
| `Pidgey\Model\` | Entities and values: Organization, User, SmtpConnection, ApiToken, Message, SendOutcome. |
| `Pidgey\Smtp\` | SMTP transport: connect, TLS/SSL, auth, send, map failures to the error taxonomy. |
| `Pidgey\Infra\` | Configuration loading (env vars), PDO factory, logger factory (monolog), migrations runner. |

Wiring is manual (a small container/factory class), with no DI framework. Runtime
dependencies are limited to `php` and `monolog/monolog`
(see [architecture/dependencies.md](architecture/dependencies.md)).

### `source/public/`

The Apache document root (web root): only the `index.php` front controller and the `.htaccess`.
No frontend files live here.

### `source/static/`

The built frontend (`index.html` and `assets/`), git-ignored and outside the web root. Filled by
`make build-fe-release` in dev and uploaded with the release in production, it is served at `/`
by `StaticFileHandler` (see [request-handler/static-files.md](request-handler/static-files.md)).

### `source/migrations/`

Plain SQL files `NNNN_description.sql`, applied in order by a script in `scripts/`.
Applied versions are recorded in `schema_migrations`.

### `source/tests/`

PHPUnit `unit/` and `integration/` suites (integration runs against MySQL and Mailpit).

### `frontend/assets/js/`

`main.jsx` mounts `App.jsx` (hash router). `client/` holds the API clients: `ApiClient`
is the base, and domain clients cover each endpoint. `components/` holds screens and shared UI.
`utils/` holds pure helpers. Jasmine specs mirror this tree under `frontend/specs/`.
Components never call `fetch` directly. See [frontend.md](frontend.md).
