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
request: Apache [rewrites](../request-handler/rewrite.md) requests that are not existing files or directories to it.
The web root holds no frontend files, so in practice every request but a dotfile reaches PHP. How a
request flows from the rewrite to the response (bootstrap, routing, request handlers,
middlewares) is specified in [request-handler.md](../request-handler.md), the source of truth
for the request flow.

Routing rule, shared by development and production:

- **`*.json` is the API**, anywhere in the path tree (e.g. `/admin/users/search.json`).
- **`POST /v1/messages`** is the only non-`.json` API route (see
  [api/conventions/format.md](../api/conventions/format.md#paths)). API paths are otherwise
  root-relative with no prefix.
- **Other `GET` paths are the frontend.** The built single-page application lives in
  `source/static/`, outside the web root, and is served at `/` by the PHP static-file handler:
  `GET /` → `index.html`, `GET /assets/*` → the hashed bundles, and any other extensionless
  `GET` outside `/v1/` → `302` to `/#<path>` (see
  [request-handler/static-files.md](../request-handler/static-files.md) and
  [request-handler/routing.md](../request-handler/routing.md#get-fallback)). The build itself
  is described in [frontend/build.md](../frontend/build.md).

## Layers

| Layer | Namespace (indicative) | Responsibility |
| --- | --- | --- |
| HTTP | `Pidgey\Http\` | Request/Response objects, router, request handlers (`RequestHandler`, `HandlerFactory`), middlewares (authentication guards: cookie-session gate and Bearer token, see [api/authentication.md](../api/authentication.md)), JSON encoding. See [request-handler.md](../request-handler.md). No SQL, no SMTP. |
| Services | `Pidgey\Service\` | Business logic: registration, login, token issuing/revocation, sending a message. Orchestrates repositories and transport. |
| Repositories | `Pidgey\Repository\` | All database access through raw PDO with prepared statements. Return plain entity/value objects. See [persistence/repositories.md](../persistence/repositories.md). |
| Entities / values | `Pidgey\Model\` | Organization, User, SmtpConnection, ApiToken, Message, SendOutcome. Table entities follow [persistence/entities.md](../persistence/entities.md). |
| SMTP transport | `Pidgey\Smtp\` | Opens the connection, negotiates TLS/SSL, authenticates, sends, maps failures to the error taxonomy. See [email](../email.md). |
| Infrastructure | `Pidgey\Infra\` | Configuration loading, PDO factory, `Transaction` helper (see [persistence/connection.md](../persistence/connection.md)), logger factory, migrations runner, wall clock (`WallClock`, see [request-handler/request-response/errors.md](../request-handler/request-response/errors.md#wall-clock)). |

Dependencies flow downward only: HTTP → Services → (Repositories, Transport).
Wiring is done manually (a small container/factory class: `Pidgey\Container` and `Pidgey\Http\HandlerFactory`, see
[request-handler/handlers.md](../request-handler/handlers.md#factory-and-container)), no DI framework.

## Configuration

Configuration is read from environment variables. Every variable, its default and its effect
are listed in [configuration.md](configuration.md).

**Pending decision:** whether the SMTP transport is hand-written over sockets or
uses a library (this would add a runtime dependency and requires changing
[dependencies.md](dependencies.md)). Working assumption: hand-written, PHP streams.
