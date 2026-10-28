# Issue: Specs: request-handler — Request and Response models

## Description

Write `docs/agents/specs/request-handler/request-response.md`: the request and response models, the processing request (route params, attached data, early response), the error responses and how a response is sent. It replaces the stub created by #56.

Part of Epic #55: specify how HTTP requests flow through the Pidgey backend.

```
Apache + .htaccess → source/public/index.php → Request → routing → middlewares (pre hooks) → request handler → middlewares (post hooks) → Response → ResponseSender
```

The specs live under `docs/agents/specs/request-handler/` and are indexed by `docs/agents/specs/request-handler.md`.

**Rules for every part:**
- **Spec only.** No PHP, `.htaccess` or tests are written here. Implementation issues come later.
- **Structure only.** What each endpoint does stays in `docs/agents/specs/api/endpoints/`.
- **Format:** follow the conventions in `docs/agents/specs.md`. The file starts with a title and a one-line `Purpose:` statement, open questions are marked **Pending decision**, and everything is in English.

**Decisions already made in #55:**
- **Hand-written.** Classes live in `Pidgey\Http\`. No router or middleware library, and no new runtime dependency (`architecture/dependencies.md`).
- **Routing.** Routes are registered dev-API style (method + path with `:param` → handler class). They are ordered, the first match wins, and there is a guaranteed 404 fallback. The router captures the `:param` values and passes them to the handler.
- **Handlers.** Tent-style base class (a `final` entry method calls an abstract processing method). A new instance is built per request through a factory that injects its dependencies.
- **Middlewares.** Tent-style pre/post hooks (`processRequest` / `processResponse`).
  - A request hook can answer early by setting a response on the processing request.
  - Middlewares are attached globally and per route.
  - Their order follows the guard order in `api/conventions/format.md`.
- **Error handling.** Exceptions are turned into the JSON error envelope around the whole dispatch, not by a middleware.

**Reference:** `/Users/darthjee/messages/php-request-handler/request-response.md` (Tent's `Request`, `ProcessingRequest`, `Response` and `ResponseSender`).

**Sequencing:** this is the shared contract for routing (#59), handlers (#60), middlewares (#61) and the front controller (#62). Do it right after the index (#56, merged).

## Problem

`request-response.md` is still a stub, so no other part of #55 can rely on how a request is read or how a response is built and sent. Copying Tent as it is would bring in behaviours that don't fit Pidgey:
- `getallheaders()` doesn't see `Authorization` under Apache, and #57 chose `$_SERVER` with `CGIPassAuth`.
- A mutable request.
- A string-keyed attachment store.
- Plain-text 404s that don't use the JSON error envelope of `api/conventions/errors.md`.

## Expected Behavior

- `docs/agents/specs/request-handler/request-response.md` replaces its stub with the contract in the Solution below. It keeps the title and the `Purpose:` line, refining the line if needed and keeping the index table row of `request-handler.md` in sync.
- The spec is the single source of truth for:
  - `Request` / processing request: method, path, query, headers, cookies, raw and JSON body, route params, attached data, early response;
  - `Response`, `JsonResponse`, `NoContentResponse`, `ErrorResponse`, and `SetCookie`;
  - the `HttpException` family and `ErrorFormatter`;
  - the `WallClock` interface;
  - `ResponseSender`.
- The flow diagram in `request-handler.md` matches the early-response semantics: after an early answer, all response hooks still run.
- `grep -n 'content to be specified in #58' docs/agents/specs/request-handler/` returns nothing.
- Spec only: no PHP or tests.

## Solution

### Sending (`ResponseSender`)

- `send(Response $response)` calls the status emitter (`http_response_code()`) with `status()`, then the header emitter `header($line, false)` for each header line, then echoes the body.
- The `false` (no replace) is deliberate: several `Set-Cookie` lines survive.
- Both emitters are injectable callables, so tests can see what was sent.

### Testing hooks

- `Request` is built from the PHP globals by default. Every value (`method`, `uri`, `headers`, `cookies`, `body`) can be overridden through constructor options, so tests need no web server.
- `ResponseSender` takes injectable emitters. `ErrorFormatter` takes a `WallClock`, which tests freeze.

### Header access contract

Headers are read **only from `$_SERVER`** (decided in #57: `CGIPassAuth On` makes `Authorization` visible there). `getallheaders()` is never used.

- **`header(string $name, ?string $default = null): ?string`**
  - The lookup ignores case. The name is turned into a `$_SERVER` key: upper-case it, replace `-` with `_`, then add the `HTTP_` prefix. `Content-Type` and `Content-Length` are the exceptions: they map to `CONTENT_TYPE` and `CONTENT_LENGTH`, with no prefix. `header('content-type')`, `header('Content-Type')` and `header('CONTENT_TYPE')` all hit the same key.
  - A header that wasn't sent returns `$default`, which is `null` unless given. A header sent with an empty value returns `""`, never the default.
  - The value is returned exactly as received, with no trimming and no splitting. Apache joins a repeated header into one string with `, ` (`X-Dup: a` + `X-Dup: b` → `"a, b"`). A caller that needs a list splits it itself. Nothing in v1 needs that: the origin check, the gate and the body parser all read single-valued headers.
- **`headers(): array<string, string>`**: every request header, keyed by its lower-case, dash-separated name (`content-type`, `x-dup`), with values as above. Logging uses this map, and it must leave out `authorization` and `cookie` (see `api/authentication.md`).
- **Host:** read with `header('Host')`. There is no `domain()` method; the origin check parses host and port itself.
- **Test override:** the constructor accepts `['headers' => ['Content-Type' => 'application/json', ...]]`. The names in that map are normalized the same way, so `header()` and `headers()` behave the same as they do on a real request.
- **Documented limits** (checked on `php:8.4-apache`):
  - Header names containing `_` are dropped by Apache 2.4 and never arrive.
  - The case of a header name is lost (`x-lower` → `HTTP_X_LOWER`).
  - `Authorization` arrives only because of `CGIPassAuth` (#57).

### JSON body parsing

- **Raw body:** `body(): string` reads `php://input` once and caches it. It can be overridden through the constructor (`['body' => '...']`) for tests.
- **Parsed body:** `json(): array` is **lazy**. It parses on the first call and caches the result, so it never runs before the guards: an anonymous request with a broken body to a protected route still gets `401` (guard order in `api/conventions/format.md`). Parse failures throw exceptions, and the error handling around the whole dispatch (#55) turns them into the error envelope (`api/conventions/errors.md`: category code plus the standard reason phrase).
  - **Empty body** (length 0): returns `[]`, whatever the `Content-Type`. Endpoints that send no body (e.g. `DELETE /auth/logoff.json`) just work.
  - **Media type:** for a non-empty body, it must be `application/json`, compared without regard to case. Otherwise **415**. A `charset` parameter is allowed only when it is `utf-8`, compared without regard to case; any other charset also answers **415**.
  - **Size limit:** a fixed constant of **1 MiB**. A body over the limit answers **413**. It is checked against `Content-Length` before reading, and the read itself is capped at limit + 1 bytes, which covers bodies without a `Content-Length`.
  - **Malformed JSON or invalid UTF-8:** `json_decode` with `JSON_THROW_ON_ERROR` and a depth limit of 64. A failure answers **400**.
  - **Top level must be a JSON object.** A top-level array (including `[]`), string, number, boolean or `null` answers **400**. The check is made on the decoded value itself, because `[]` and `{}` look the same once decoded into an array.
  - **Unknown fields** are kept. Dropping them is validation's job (`api/conventions/validation.md`).
- **Exceptions:** `Pidgey\Http\` defines an exception per status, at least `BadRequestException` (400), `PayloadTooLargeException` (413) and `UnsupportedMediaTypeException` (415). Each carries its status, and the error formatter maps them (see the error-responses section).
- **Rejected alternatives:**
  - A `BodyParser` middleware placed just before validation: routes without a body would need it switched off, and the parsed body would add to the attached data.
  - Eager parsing in `Request`: it would answer 400 before 401 and break the guard order.

### Cookies

#### Reading

- **`cookie(string $name, ?string $default = null): ?string`**, backed by `$_COOKIE` (PHP's own cookie parser).
  - When the value is an **array** (e.g. `access_token[x]=…`), it returns `$default`, so an array never reaches the JWT code.
  - The constructor accepts `['cookies' => [...]]` for tests.
  - The gate reads `cookie('access_token')`. `headers()` still leaves out `cookie`.
- **Documented behaviour** (checked on `php:8.4-apache`):
  - With a duplicate name, the first one wins (`access_token=A; access_token=B` → `A`).
  - Values are percent-decoded (`a%3Db+c` → `a=b+c`; `+` is kept).
  - PHP rewrites `.` and spaces in names to `_`, and `[` starts an array. Pidgey's cookie names (`access_token`, and the pending `__Host-access_token`) are not affected.
  - Two separate `Cookie` headers are joined by Apache with `, `, and PHP splits only on `;`, so the second cookie is lost. Browsers never do this: HTTP/1.1 sends a single `Cookie` header, and `mod_http2` rejoins HTTP/2 cookie headers with `; `. A hand-made client that sends two headers just gets `401`.
- **Rejected alternative:** parsing `HTTP_COOKIE` by hand. It avoids the renaming and the `,` problem, but it adds code to test and fixes nothing that matters for Pidgey's cookie names.

#### Setting

- PHP's `setcookie()` is **never** used: it writes the header directly, skips the `Response`, and can't be tested.
- A cookie is one more `Set-Cookie:` line in `Response::headers()`. It is sent with `header($line, false)`, so several cookies survive.
- A **`SetCookie`** value object builds that line from name, value, `Path`, `Max-Age`, `Expires`, `HttpOnly`, `Secure` and `SameSite`.
  - It has a `clear()` form for logout: `access_token=; Path=/; Max-Age=0; Expires=<past date>`, plus the same attributes as in `api/authentication/access-token.md`.
  - It rejects CR, LF, `;` and `,` in names and values.

### Attached data API

Middlewares attach typed objects to the processing request. In v1, the gate (cookie routes) attaches the authenticated user (`{ id, sid, username, isAdmin }` plus `organizationId`, see `api/authentication/gate.md`), and the Bearer check (`/v1`) attaches the API token (`{ id, organizationId, scopes }`, see `api/authentication/api-tokens.md`). Route `:param` values are **not** attached data: routing captures them and they are read with `param(string $name): ?string`.

The store is **keyed by class**:

- **`attach(object $value): void`**: stores the object. Attaching a second object of the same class throws a `LogicException`, so nothing is overwritten silently and a later middleware can't replace the gate's user.
- **`attached(string $class): ?object`**: returns the attached object that is an `instanceof $class` (a class or an interface), or `null`. If more than one attached object matches, it throws a `LogicException`.
- **`require(string $class): object`**: the same lookup, but a missing object throws a `LogicException`. That only happens on a misconfigured route (e.g. a handler that needs the user on a route with no gate), and it answers `500 INTERNAL_ERROR`, never `401`.
- `Pidgey\Http\` does not depend on any auth class. Callers pass class names (`attached(AuthenticatedUser::class)`), so the return type reads clearly to the IDE and to reviewers.
- **Rejected alternatives:**
  - A generic string-keyed store (Tent's style): untyped, keys can be mistyped, and `mixed` everywhere.
  - Typed accessors such as `user()` and `apiToken()`: `Pidgey\Http\` would depend on the auth models, and every new attachment would need new methods.

**Hand-off to #61:** organization scope needs an organization id from either the user or the token. Both models should implement a small `Caller` interface with `organizationId()`, so scoped handlers use `require(Caller::class)`.

### Early-response contract

This issue owns the contract; #61 (middlewares) uses it. Pidgey follows Tent (`RequestHandler::handleRequest`).

- **On the processing request:**
  - `respond(Response $response): void` answers early. Calling it when a response is already set throws a `LogicException`.
  - `hasResponse(): bool` and `response(): ?Response`.
- **Semantics once a response is set:**
  - The dispatch loop checks `hasResponse()` after each request hook. No later request hook runs.
  - The handler is not called.
  - **All response hooks run**, in registration order, including those of middlewares whose request hook was skipped (Tent's behaviour). When nothing answers early, the handler's response goes through the same response hooks in the same order.
- **Early responses are for non-error answers**, such as a CORS preflight (`OPTIONS` → `204`) or, later, a `304`. Guard failures (`401`, `403`) **throw**, so every error goes through the single error formatter (#55). This also keeps the gate's uniform `401` byte for byte the same.
- **Rejected alternatives:**
  - Onion order (only the response hooks of middlewares that ran, in reverse order).
  - No response hooks after an early answer.

**Hand-off to #61:** error responses built from exceptions don't go through the middleware chain. If CORS headers are added by a response hook, a cross-origin admin UI (`ALLOWED_ORIGINS`) would get `401`s without CORS headers, and the browser would hide the status from the frontend. #61 decides whether error responses also go through the response hooks.

### Error responses

#### Response classes (`Pidgey\Http\`)

- **`Response`**: `body(): string`, `status(): int` (default `200`), `headers(): list<string>` (header **lines**) and `addHeader(string $line)`. `addHeader` rejects CR and LF, which guards every header line against header injection, not only cookies.
- **`JsonResponse extends Response`**: `(array|JsonSerializable $data, int $status = 200)`.
  - Encoded with `JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
  - Adds `Content-Type: application/json`.
  - **No key conversion:** handlers build `camelCase` keys themselves (`api/conventions/format.md`).
- **`NoContentResponse`**: `204`, with no body and no `Content-Type` (e.g. logoff).
- **`ErrorResponse extends JsonResponse`**: builds the envelope of `api/conventions/errors.md` from `(status, code, message, ?details, ?smtp, timestamp)`.
  - `error.details` and `error.smtp` are omitted when null.
  - `statusCode` repeats the status.
  - `timestamp` is ISO 8601 UTC with milliseconds.

#### Exceptions

- **`HttpException`** carries `(status, ?code, ?message, ?details, ?smtp)`.
- Subclasses per status:
  - `BadRequestException` (400)
  - `UnauthorizedException` (401)
  - `ForbiddenException` (403)
  - `NotFoundException` (404)
  - `ConflictException` (409)
  - `PayloadTooLargeException` (413)
  - `UnsupportedMediaTypeException` (415)
  - `UnprocessableEntityException` (422)
  - `LockedException` (423)
- **`ValidationFailedException(list<string> $details)`**: `400`, `VALIDATION_FAILED`, with the message being `details` joined by `"; "`.
- A specific code (e.g. `USERNAME_TAKEN`, `USER_DENIED`) is passed to the constructor. The SMTP failures (`502`, `503`, `504`, see `email/failures.md`) use `HttpException` directly with their codes. Sanitizing `error.smtp` stays with the email layer.

#### `ErrorFormatter`

The single error formatter: `format(Throwable $error): ErrorResponse`.

- **`HttpException`:** uses its fields.
  - A missing code falls back to the category code from `errors.md`'s table, or `HTTP_<status>` for any other status.
  - A missing message falls back to the standard reason phrase (e.g. `Bad Request`). The body-parser failures (`400`, `413`, `415`) rely on this.
- **Any other `Throwable`** (including the `LogicException`s from the attached-data API): `500 INTERNAL_ERROR` / `Internal server error`. The real error is logged through Monolog and never sent.
- **Who calls it:** the front controller (#62), around the whole dispatch (#55).

#### Wall clock

The existing `Pidgey\Infra\Clock` is monotonic (`hrtime()`), meant for timeouts, and can't produce a wall-clock `timestamp`.

- A new **`Pidgey\Infra\WallClock`** interface: `now(): DateTimeImmutable`, in UTC. `SystemWallClock` is the implementation.
- `ErrorFormatter` gets it injected. Sessions (`api/authentication/sessions.md#clock`) use it too, and tests can freeze the time.
- **Rejected alternatives:**
  - Adding `nowUtc()` to `Clock`: it mixes monotonic and wall time.
  - `psr/clock`: a new runtime dependency (`architecture/dependencies.md`).

**Hand-off to #59:** the guaranteed fallback throws `NotFoundException` with the message `Cannot <METHOD> <path>` (as in `errors.md`). A known path with the wrong method also answers `404`; there is no `405`.

### Method, path and query

- **`method(): string`**: `$_SERVER['REQUEST_METHOD']`, upper-case.
- **`path(): string`**: `rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))` (hand-off from #57: Apache passes `REQUEST_URI` raw).
  - Defaults to `/` when `REQUEST_URI` is missing or can't be parsed.
  - `rawurldecode` keeps `+` as is (`%2B` → `+`, `%20` → space).
  - `%2F` never arrives: Apache answers `404` (`AllowEncodedSlashes Off`, #57).
  - A decoded NUL byte (`%00`) answers `400` (`BadRequestException`).
- **`query(): string`**: the raw query string (default `''`). **`queryParams(): array`**: the `parse_str` output. Request bodies never go in query strings (`api/conventions/format.md`), so this only matters for `GET`.
- **No changes to the request:** apart from attachments and the early response, the processing request can't be changed. There is no Tent-style `setRequestPath()` / `setHeader()`, because Pidgey has no rewrite rules.

### Hand-offs to other parts of #55

- **#59 (routing):** the fallback throws `NotFoundException` (`Cannot <METHOD> <path>`). A wrong method on a known path is `404`, never `405`. Routing fills the `param()` values.
- **#61 (middlewares):**
  - A `Caller` interface (`organizationId()`) implemented by the authenticated user and the API token.
  - Whether error responses built from exceptions go through the response hooks (CORS headers on `401`s).
  - Guards throw for errors; `respond()` is for non-error early answers.
- **#62 (front controller):** wraps the whole dispatch, calls `ErrorFormatter` on any `Throwable` and sends through `ResponseSender`. The real error behind a `500` is logged through Monolog.
- **Sessions (`api/authentication/sessions.md#clock`):** use the same `WallClock`.

## Benefits

- Every other part of #55 builds on one fixed, testable contract for requests and responses.
- Security rules are built into the models instead of left to each handler:
  - array cookies are rejected;
  - CR/LF can't get into header lines;
  - the body size is capped;
  - every error uses one envelope;
  - the `401` stays uniform.
- No new runtime dependency.
