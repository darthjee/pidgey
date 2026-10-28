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
request: Apache [rewrites](../request-handler/rewrite.md) requests that are not existing files or directories to it, so
static files never reach PHP. How a request flows from the rewrite to the response
(bootstrap, routing, request handlers, middlewares) is specified in
[request-handler.md](../request-handler.md), the source of truth for the request flow.

The built frontend lives under `admin/` in the web root and is served at `/admin/` by
Apache directly (see [frontend/build.md](../frontend/build.md)). Non-`.json` paths under
`/admin/` are reserved for the UI; `*.json` paths are always routed to the API, anywhere in
the tree, including under `/admin/` (e.g. `/admin/users/search.json`). The UI never ships
`.json` files under `/admin/`, so no rewrite change is needed. API paths are root-relative
with no prefix; `POST /v1/messages` is the only prefixed route (see
[api/conventions/format.md](../api/conventions/format.md#paths)).

## Layers

| Layer | Namespace (indicative) | Responsibility |
| --- | --- | --- |
| HTTP | `Pidgey\Http\` | Request/Response objects, router, request handlers (`RequestHandler`, `HandlerFactory`), middlewares (authentication guards: cookie-session gate and Bearer token, see [api/authentication.md](../api/authentication.md)), JSON encoding. See [request-handler.md](../request-handler.md). No SQL, no SMTP. |
| Services | `Pidgey\Service\` | Business logic: registration, login, token issuing/revocation, sending a message. Orchestrates repositories and transport. |
| Repositories | `Pidgey\Repository\` | All database access through raw PDO with prepared statements. Return plain entity/value objects. |
| Entities / values | `Pidgey\Model\` | Organization, User, SmtpConnection, ApiToken, Message, SendOutcome. |
| SMTP transport | `Pidgey\Smtp\` | Opens the connection, negotiates TLS/SSL, authenticates, sends, maps failures to the error taxonomy. See [email](../email.md). |
| Infrastructure | `Pidgey\Infra\` | Configuration loading, PDO factory, logger factory, migrations runner, wall clock (`WallClock`, see [request-handler/request-response/errors.md](../request-handler/request-response/errors.md#wall-clock)). |

Dependencies flow downward only: HTTP → Services → (Repositories, Transport).
Wiring is done manually (a small container/factory class: `Pidgey\Container` and `Pidgey\Http\HandlerFactory`, see
[request-handler/handlers.md](../request-handler/handlers.md#factory-and-container)), no DI framework.

## Configuration

Configuration is read from environment variables. Every variable, its default and its effect
are listed in [configuration.md](configuration.md).

**Pending decision:** whether the SMTP transport is hand-written over sockets or
uses a library (this would add a runtime dependency and requires changing
[dependencies.md](dependencies.md)). Working assumption: hand-written, PHP streams.
