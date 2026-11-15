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
| Features | `Pidgey\Auth\`, `Pidgey\Email\`, … | Business logic, one namespace per feature (no `Pidgey\Service\` namespace): registration, login, token issuing/revocation in `Pidgey\Auth\`; the send flow, the message and MIME builder, the send outcome and the send-time checks (`SMTP_INSECURE_CREDENTIALS`, `SMTP_HOST_NOT_ALLOWED`, `SMTP_PASSWORD_UNREADABLE`) in `Pidgey\Email\`. Orchestrates repositories and the SMTP transport. |
| Repositories | `Pidgey\Repository\` | All database access through raw PDO with prepared statements. Return plain entity/value objects. See [persistence/repositories.md](../persistence/repositories.md). |
| Entities / values | `Pidgey\Model\` | Organization, User, SmtpConnection, ApiToken. (`Message` and `SendOutcome` are send-flow values in `Pidgey\Email\`.) Table entities follow [persistence/entities.md](../persistence/entities.md). |
| SMTP transport | `Pidgey\Smtp\` | The protocol layer: transport interface and implementation (`Transport` / `SmtpTransport`, wired by `Pidgey\Container::smtpTransport()`), the stream interface and its socket implementation, reply and reply parser, session deadline, failure and failure code. Opens the connection, negotiates TLS/SSL, authenticates, sends, classifies failures into the error taxonomy (see [email](../email.md)). Knows nothing about HTTP, organizations, repositories or logging; depends only on `Pidgey\Infra\` (`Clock`, `SecretString`). Never reads `Env`: its settings arrive as `Pidgey\Smtp\SessionSettings`, built by `Pidgey\Container`. Its inputs are its own types (`Target`, `Credentials`, `Envelope`); the encryption is a `TlsMode` (`Plain` / `StartTls` / `Implicit`), onto which the send flow maps the model's `none` / `tls` / `ssl`; the transport never takes `Pidgey\Model\SmtpEncryption`. |
| Infrastructure | `Pidgey\Infra\` | Configuration loading, PDO factory, `Transaction` helper (see [persistence/connection.md](../persistence/connection.md)), logger factory, migrations runner, wall clock (`WallClock`, see [request-handler/request-response/errors.md](../request-handler/request-response/errors.md#wall-clock)) and monotonic clock (`Clock`, for the SMTP session deadline), the `SecretString` value object (holds a secret such as the decrypted SMTP password, see [Logging](#logging) and [email/send-flow.md](../email/send-flow.md#secret-hygiene)), `Runtime::harden()` (boot hardening, see [configuration.md](configuration.md#smtp-transport)) and `AppPath` (the app root, `source/`, against which relative paths are resolved). |

Dependencies flow downward only: HTTP → feature namespaces → (Repositories, `Pidgey\Smtp\`).
`Pidgey\Email\` depends on `Pidgey\Smtp\`, never the reverse; `Pidgey\Smtp\` depends
only on `Pidgey\Infra\` (`Clock`, `SecretString`). The HTTP exception carrying
`error.smtp` stays in `Pidgey\Http\` and maps the failure code to the status and fixed
message ([email/failures.md](../email/failures.md#fixed-messages)).
Wiring is done manually (a small container/factory class: `Pidgey\Container` and `Pidgey\Http\HandlerFactory`, see
[request-handler/handlers.md](../request-handler/handlers.md#factory-and-container)), no DI framework.

## Logging

- `Pidgey\Infra\LoggerFactory` builds the application's **Monolog** logger:
  - `LoggerFactory::create(level, handler = ErrorLogHandler)` takes the minimum level and an
    optional handler; by default lines go to the same destination as PHP's `error_log()`.
    An unknown level is rejected with `LOG_LEVEL: invalid level`, never echoing the value;
  - `LoggerFactory::fromEnv()` reads the minimum level from `LOG_LEVEL` (default `info`, see
    [configuration.md](configuration.md#logging)).
- Channel `pidgey`; line format `pidgey.<LEVEL>: <message> <context> <extra>`. Line breaks
  are flattened to one line per record, no processors are attached, and no stack traces are
  written.
- `Pidgey\Container::logger()` builds the logger on first use (through `fromEnv()`) and
  reuses it; tests inject their own with `new Container(logger: …)`.
- **Context objects:** an object in the log context is rendered as JSON when it implements
  `JsonSerializable`, as its `__toString()` when it has one, and otherwise as its class name
  only. A value object that holds a secret (e.g. `Pidgey\Infra\SecretString`) must therefore implement
  **neither** interface, so it can only ever be logged as its class name.
- Operator log lines (see
  [api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md#operator-log)) are `info`
  lines whose message is the action name and whose context is JSON (`userId`,
  `organizationId`, `connectionId`, `fields` as names only); no value, password or token is
  ever logged.

## Configuration

Configuration is read from environment variables. Every variable, its default and its effect
are listed in [configuration.md](configuration.md).

## SMTP transport

The SMTP transport and the MIME builder are **hand-written over PHP streams**; no SMTP or
MIME library is used, so [dependencies.md](dependencies.md) is unchanged (`php` and
`monolog/monolog` only). Libraries (`symfony/mailer` + `symfony/mime`, PHPMailer,
`symfony/mime` alone) were rejected: they are opportunistic about `STARTTLS`, have a single
stream timeout and no session deadline, classify failures by exception text, bring their
own MIME encoding choices and need a real socket for tests, while
[email/send-flow.md](../email/send-flow.md) pins each of these behaviors. Protocol bugs are
covered by reply-by-reply unit tests against a fake stream and by integration tests against
Mailpit and the test CA.
