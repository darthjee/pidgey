# Backend

Purpose: define how the plain PHP backend is structured.

## Principles

- Plain PHP 8.x, **no application framework**.
- PSR-4 autoloading via Composer: namespace `Pidgey\` maps to `source/lib/`.
- `declare(strict_types=1);` in every file; code follows phpcs + slevomat rules
  (see [testing/backend.md](../testing/backend.md)).
- Logging through `monolog/monolog`.

## Entry point

A single **front controller** (`source/public/index.php`) receives every dynamic
request (Apache rewrites non-file requests to it). It bootstraps configuration,
the PDO connection and the logger, then dispatches to the router.

Static files (the built frontend under `static/`) are served directly by Apache.

## Layers

| Layer | Namespace (indicative) | Responsibility |
| --- | --- | --- |
| HTTP | `Pidgey\Http\` | Request/Response objects, router, controllers, JSON encoding, authentication middleware (session and Bearer token). No SQL, no SMTP. |
| Services | `Pidgey\Service\` | Business logic: registration, login, token issuing/revocation, sending a message. Orchestrates repositories and transport. |
| Repositories | `Pidgey\Repository\` | All database access through raw PDO with prepared statements. Return plain entity/value objects. |
| Entities / values | `Pidgey\Model\` | Organization, User, SmtpConnection, ApiToken, Message, SendOutcome. |
| SMTP transport | `Pidgey\Smtp\` | Opens the connection, negotiates TLS/SSL, authenticates, sends, maps failures to the error taxonomy. See [email](../email.md). |
| Infrastructure | `Pidgey\Infra\` | Configuration loading, PDO factory, logger factory, migrations runner. |

Dependencies flow downward only: HTTP → Services → (Repositories, Transport).
Wiring is done manually (a small container/factory class), no DI framework.

## Configuration

Configuration is read from environment variables (database DSN/credentials,
secret key, log level). Development values live in `docker_volumes/configuration`
or `.env` files used by docker-compose.

**Pending decision:** whether the SMTP transport is hand-written over sockets or
uses a library (this would add a runtime dependency and requires changing
[dependencies.md](dependencies.md)). Working assumption: hand-written, PHP streams.
