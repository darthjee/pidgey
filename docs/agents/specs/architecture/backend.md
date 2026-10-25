# Backend

Purpose: define how the plain PHP backend is structured.

## Principles

- Plain PHP 8.4, **no application framework**.
- PSR-4 autoloading via Composer: namespace `Pidgey\` maps to `source/lib/`.
- `declare(strict_types=1);` in every file; code follows phpcs + slevomat rules
  (see [testing/backend.md](../testing/backend.md)).
- Logging through `monolog/monolog`.

## Entry point

A single **front controller** (`source/public/index.php`) receives every dynamic
request (Apache rewrites requests that are not existing files or directories to it). It bootstraps configuration,
the PDO connection and the logger, then dispatches to the router.

Static files are served directly by Apache. The built frontend lives under `admin/` in the web
root and is served at `/admin/` (see [frontend/build.md](../frontend/build.md)). Non-`.json`
paths under `/admin/` are reserved for the UI; `*.json` paths are always routed to the API,
anywhere in the tree, including under `/admin/` (e.g. `/admin/users/search.json`). The UI never
ships `.json` files under `/admin/`, and since Apache already rewrites requests that are not
existing files to the front controller, no rewrite change is needed. API paths are
root-relative with no prefix; `POST /v1/messages` is the only prefixed route (see
[api/conventions/format.md](../api/conventions/format.md#paths)).

## Layers

| Layer | Namespace (indicative) | Responsibility |
| --- | --- | --- |
| HTTP | `Pidgey\Http\` | Request/Response objects, router, controllers, JSON encoding, authentication guards (cookie-session gate and Bearer token, see [api/authentication.md](../api/authentication.md)). No SQL, no SMTP. |
| Services | `Pidgey\Service\` | Business logic: registration, login, token issuing/revocation, sending a message. Orchestrates repositories and transport. |
| Repositories | `Pidgey\Repository\` | All database access through raw PDO with prepared statements. Return plain entity/value objects. |
| Entities / values | `Pidgey\Model\` | Organization, User, SmtpConnection, ApiToken, Message, SendOutcome. |
| SMTP transport | `Pidgey\Smtp\` | Opens the connection, negotiates TLS/SSL, authenticates, sends, maps failures to the error taxonomy. See [email](../email.md). |
| Infrastructure | `Pidgey\Infra\` | Configuration loading, PDO factory, logger factory, migrations runner. |

Dependencies flow downward only: HTTP → Services → (Repositories, Transport).
Wiring is done manually (a small container/factory class), no DI framework.

## Configuration

Configuration is read from environment variables. Every variable, its default and its effect
are listed in [configuration.md](configuration.md).

**Pending decision:** whether the SMTP transport is hand-written over sockets or
uses a library (this would add a runtime dependency and requires changing
[dependencies.md](dependencies.md)). Working assumption: hand-written, PHP streams.
