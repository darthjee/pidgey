# Architecture

> This document summarizes the target architecture. The authoritative details are in
> the specs, starting at [specs/architecture.md](specs/architecture.md). If this
> summary and a spec disagree, the spec wins.

## Overview

Pidgey is a single PHP application served by Apache, backed by MySQL 9:

- **Backend:** plain PHP 8.4 with **no application framework**. A single front controller
  (`source/public/index.php`) handles every dynamic request: the JSON API (Bearer-token
  authenticated) and the admin session endpoints.
- **Frontend:** a React 19 admin UI built with Vite. Its `dist/` output is released into
  `source/public/admin` (`make build-fe-release`) and served by Apache at `/admin/`.
- **Database:** MySQL 9 accessed only through raw PDO with prepared statements (no ORM),
  using forward-only plain SQL migrations.
- **Email:** synchronous delivery. Each send request opens a connection to the
  organization's active SMTP server and returns the outcome in the HTTP response.
  There is no queue and no retry.

See [specs/overview/components.md](specs/overview/components.md) for the component map.

## Source Code Layout

All backend source code lives under `source/` (*planned*). Frontend code lives under
`frontend/` (*planned*).

### `source/lib/` — namespace `Pidgey\`

Layers, with dependencies flowing downward only (HTTP → Services → Repositories / SMTP):

| Namespace | Responsibility |
| --- | --- |
| `Pidgey\Http\` | Request/Response, router, controllers, JSON encoding, session and Bearer-token auth middleware. No SQL, no SMTP. |
| `Pidgey\Service\` | Business logic (registration, login, token issuing/revocation, sending a message). Orchestrates repositories and transport. |
| `Pidgey\Repository\` | All SQL, through PDO prepared statements. Every organization-scoped query filters by `organization_id`. |
| `Pidgey\Model\` | Entities and values: Organization, User, SmtpConnection, ApiToken, Message, SendOutcome. |
| `Pidgey\Smtp\` | SMTP transport: connect, TLS/SSL, auth, send, map failures to the error taxonomy. |
| `Pidgey\Infra\` | Configuration loading (env vars), PDO factory, logger factory (monolog), migrations runner. |

Wiring is manual (a small container/factory class), with no DI framework. Runtime
dependencies are limited to `php` and `monolog/monolog`
(see [specs/architecture/dependencies.md](specs/architecture/dependencies.md)).

### `source/public/`

The Apache document root: `index.php` front controller plus built frontend assets under `admin/` (served at `/admin/`, git-ignored for now).

### `source/migrations/`

Plain SQL files `NNNN_description.sql`, applied in order by a script in `scripts/`.
Applied versions are recorded in `schema_migrations`.

### `source/tests/`

PHPUnit `unit/` and `integration/` suites (integration runs against MySQL and Mailpit).

### `frontend/assets/js/`

`main.jsx` mounts `App.jsx` (hash router). `client/` holds the API clients: `ApiClient`
is the base, and domain clients cover each endpoint. `components/` holds screens and shared UI.
`utils/` holds pure helpers. Jasmine specs mirror this tree under `frontend/specs/`.
Components never call `fetch` directly. See [specs/frontend.md](specs/frontend.md).
