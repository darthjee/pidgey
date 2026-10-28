# Issue: Specs: request-handler — handler base class

## Description

Write `docs/agents/specs/request-handler/handlers.md`: the contract of the request handler base class (`Pidgey\Http\RequestHandler`), the `HandlerFactory`, the shape of the `Pidgey\Container`, the handler lifecycle, the integer-id helper, where body validation runs, and how handlers report errors.

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

Reference: `/Users/darthjee/messages/php-request-handler/handlers.md` (Tent's `RequestHandler`).

Sequencing: after request-response (#58) and routing (#59). Can proceed in parallel with middlewares (#61).

## Problem

`handlers.md` is still a stub. Routing (#59) hands off to it:
- the handler factory that builds the handler and its middlewares from a `RouteMatch`;
- the integer-id helper and where it lives;
- the base class that `Router::add()` checks with `is_subclass_of`.

`routing.md` also carries a pending decision (status for a malformed `:id`: 400 or 404) that this issue resolves. Without this spec, the implementation issues have no contract for how handlers are built, wired to their dependencies, run with their hooks, and report errors.

## Expected Behavior

### Base class and entry method

- **Name:** `Pidgey\Http\RequestHandler` (abstract). Not `Handler`, which would read ambiguously next to Monolog's `*Handler` classes.
- **Entry method (option A, Tent-style):** `final public function handle(ProcessingRequest $request, array $middlewares): Response`.
  - It runs the request hooks in order and stops after the first hook that sets an early response (`hasResponse()`).
  - It uses the early response when one is set, otherwise it calls `process()`.
  - It then runs **all** response hooks, in the same order, including after an early response (as in `processing-request.md`).
  - `$middlewares` is the already-built, already-merged list (global first, then per-route, in guard order). The factory/dispatcher builds and merges it; the handler never knows which hooks are global.
  - The list is an argument, not a property, so the handler holds no per-request state. Tests pass `[]`.
- **Processing method:** `abstract protected function process(ProcessingRequest $request): Response`. This fixes Tent's `processsRequest` typo. It is protected, so `handle()` is the only public surface.
- **Errors:** `handle()` catches nothing. Exceptions from a request hook, `process()` or a response hook propagate unchanged to the error wrapper in the front controller (#62). Whether error responses pass through response hooks stays with #61 (the CORS hand-off).
- **Hook signatures** are owned by #61. This spec only uses `processRequest(ProcessingRequest): void` and `processResponse(ProcessingRequest, Response): Response`, and follows #61 if they change.
- **Rejected:**
  - a separate `Pipeline` that runs the hooks, which would leave the final entry method empty and depart from #55;
  - Tent's `prependMiddlewares()`/`buildMiddlewares()` setters, which mutate the handler after construction.

### Factory and dependency injection

- **`build()` on each class:** `RequestHandler` declares `public static function build(Container $container): static`, which defaults to `return new static();`.
  - A handler with dependencies overrides it (e.g. `return new self($c->smtpConnectionService(), $c->logger());`). Its constructor stays fully typed, and the container is only touched in `build()`.
  - A subclass that adds constructor arguments **must** override `build()`. The factory test catches a missing override.
- **`Pidgey\Container`:** the "small container/factory class" from `architecture/backend.md`.
  - It lives in the root namespace, as the composition root. It builds the HTTP, services and infra layers, so it does not belong to any of them (`Pidgey\Infra\` would invert "dependencies flow downward").
  - **Explicit typed getters**, memoized and lazy (e.g. `pdo(): PDO`, `logger(): LoggerInterface`, `wallClock(): WallClock`). Each service is built on its first call and reused for the rest of the request.
  - Out of scope: the list of getters. #62 creates the container in the front controller, and each service's issue adds its getter.
- **Middlewares** use the same `static build(Container): static` convention. The interface itself belongs to #61 (hand-off).
- **`Pidgey\Http\HandlerFactory`**, built with the `Container`:
  - `handler(string $class): RequestHandler` calls `$class::build($container)`;
  - `middlewares(list<class-string> $classes): list<Middleware>` builds them in order;
  - the dispatcher (#62) calls both from the `RouteMatch`, concatenates the global middlewares and then the route's, and calls `handle()`.
- **Laziness and failure:**
  - only the matched route's handler and middlewares are built;
  - the container opens nothing until a getter is called, so `/health.json` never touches the database;
  - a failure while building (e.g. the database is unreachable) is an ordinary exception, which the error wrapper turns into a 500.
- **Tests:**
  - unit tests construct handlers directly with fakes (`new X($fake)`) and bypass `build()`;
  - an integration **factory test** builds every handler class registered in `config/routes.php` with the real test container.
- **Rejected:**
  - reflection autowiring (a DI framework in disguise);
  - a central class-to-closure map (one file edited per handler);
  - a generic `get(string $id): mixed` service locator (untyped);
  - `psr/container`, which would be a new runtime dependency.

### Lifecycle

- **One instance per dispatch:**
  - the factory builds the handler and its middlewares after the route matches;
  - they serve that one request and are then discarded;
  - this is unlike Tent, which reuses one handler per rule for every request that matches the rule.
- **State a handler may hold:** only injected dependencies, as `private readonly` constructor-promoted properties.
  - Per-request data (the request, params, the attached user, the parsed body) arrives through the `$request` argument and stays in local variables.
  - This is a convention checked in review.
- **No static mutable state** in handlers, middlewares or services. `static` is used only for `build()` and constants.
  - The reason is not production: under mod_fcgid, `php-cgi` processes persist, but PHP still resets statics on every request.
  - The reason is PHPUnit, which runs the whole suite in one process, so statics would leak between tests. This is the same reasoning as "no `reset()`" in `routing.md`.
- **Container scope:** one `Container` per request, created by the front controller (#62).
  - Memoized services are shared within that request (e.g. one PDO connection for the gate's session lookup and the handler's queries).
  - Nothing survives into the next request.
- **No teardown hook:** resources (PDO, SMTP sockets) close at the end of the request. The SMTP transport's own cleanup stays with the email specs.
- **Worker runtimes** (FrankenPHP worker mode, RoadRunner, Swoole) are out of scope. The per-instance and no-static rules would already hold there; only the container scope would need revisiting.

### Scope boundaries

**In scope:**
- `RequestHandler`: `handle()`, `process()`, the `build(Container)` default, and its helpers (e.g. `intParam()`).
- `HandlerFactory`: `handler()` and `middlewares()`.
- The **shape** of `Pidgey\Container`: root namespace, typed lazy memoized getters, one per request.
- The lifecycle and state rules.
- **Where** body validation runs and what it throws.
- How handlers report errors.
- An example handler and its route registration.
- Updates to sibling specs where this spec decides something they mention:
  - `routing.md`: the `intParam` pending item and the "decided in #60" pointers;
  - `request-handler.md`: the index row;
  - `architecture/backend.md`: a pointer from "small container/factory class" to `Pidgey\Container`.

**Out of scope:**

| Topic | Owner |
| --- | --- |
| What each endpoint does, and its field rules | `api/endpoints/`, `api/conventions/validation.md` |
| The middleware interface, hook signatures, global list, ordering, CORS and error responses through hooks | #61 `middlewares.md` |
| Building the `Container` and `Router`, the dispatcher loop, and calling `ErrorFormatter` | #62 `front-controller.md` |
| The container's list of getters and service wiring | each service's implementation issue |
| The `Validator` API and its rule set | the implementation issue (no separate spec: `validation.md` already fixes the observable behaviour) |
| `ProcessingRequest`, `Response` and the exceptions | #58 `request-response/` (used, not redefined) |
| Any PHP code or tests | the later implementation issues |

### Edge cases

**Wiring mistakes** are programmer errors. Each one throws `LogicException` (or another `Throwable`), which becomes a `500`:
1. **A route's handler class does not extend `RequestHandler`:** `Router::add()` rejects it (#59).
2. **A route's middleware class is not a middleware:** `HandlerFactory::middlewares()` checks each class and throws `LogicException`. The integration factory test builds every route's middlewares as well as its handler.
3. **A handler adds constructor arguments without overriding `build()`:** `new static()` throws an `ArgumentCountError`, which becomes a `500`. The factory test catches it.
4. **`intParam('id')` on a route with no `:id`:** `param()` returns `null`, and the helper throws `LogicException` (a route or handler bug, not a client error).

**Flow:**
5. **A request hook answers early:** `process()` is not called, and all response hooks run.
6. **A request hook calls `respond()` and then throws:** the exception wins and propagates. The early response is discarded.
7. **`process()` calls `$request->respond()`:** not allowed, but not guarded. `handle()` uses only `process()`'s return value. Rule: handlers return their response, and only request hooks use `respond()`.
8. **A response hook throws after `process()` committed a side effect:** the client gets a `500` although the action happened.
   - Handlers own their transactions and commit inside `process()`.
   - Response hooks must be infallible in practice (headers only). This is a constraint handed to #61.
9. **A response hook replaces the response:** allowed. The last hook's return value is sent.

**`intParam()` values:**
10. **Wrong format** (`0`, `01`, `-1`, `1.5`, `abc`, an empty string): `400 BAD_REQUEST`, `Validation failed (numeric string is expected)`, as in the endpoint specs.
11. **Well formed but larger than `PHP_INT_MAX`:** the signature is `intParam(ProcessingRequest $r, string $name, string $notFoundMessage): int`, and the helper throws `NotFoundException($notFoundMessage)` (e.g. `SMTP connection not found`). Rejected: returning `?int`, which adds a null check to every handler.
12. **Within PHP's int but beyond the column's range:** not the helper's job. The repository's `find()` returns `null`, which is the handler's ordinary `404`.

### Integer-id helper

- `intParam()` is a **protected method on `RequestHandler`**, with the signature from the edge cases above.
- Handlers call it first in `process()`, so the format check runs after the guards (hooks) and before body validation, as the endpoint specs require.
- This resolves the pending item in `routing.md` ("status for a malformed `:id`") in favour of **`400 BAD_REQUEST`**. `routing.md` must be updated: the handler rejects a malformed value with `BadRequestException('Validation failed (numeric string is expected)')`, not `NotFoundException`.

### Body validation

- Body validation runs **inside `process()`**, through a `Validator` the handler calls. It is not a middleware, so the guards (hooks) always run first (guard order step 4).
- It collects every field error and throws `ValidationFailedException(list<string> $details)` (`request-response/errors.md`). Unknown fields are dropped, and messages follow `api/conventions/validation.md`.
- The `Validator` API and rule set are left to the implementation issue.

### Exceptions

- Handlers report every error by **throwing** an `HttpException` subclass (or `ValidationFailedException`, or `HttpException` with an SMTP code). They never build an `ErrorResponse` themselves, unlike Tent's `StaticFileHandler`, which maps exceptions to responses.
- Any other `Throwable` becomes `500 INTERNAL_ERROR` through the single `ErrorFormatter` called by the front controller (#62).

### Example

The spec shows:
- `HealthHandler`, which has no dependencies, so `build()` is inherited. It returns a `JsonResponse`, and its route is `$router->get('/health.json', HealthHandler::class)`.
- A short handler with a dependency that shows a `build(Container)` override, `intParam()` and throwing `NotFoundException`.

### Alternatives considered

Recorded in each section above: a separate `Pipeline`, Tent's middleware setters, reflection autowiring, a central factory map, a generic service locator, `psr/container`, and `?int` from `intParam()`.

### Backward compatibility

None. There is no HTTP code yet (`source/public/index.php` is the placeholder).

### Testing strategy

- **Unit:**
  - handlers are constructed directly with fakes and exercised through `handle($request, [])` with a hand-built `ProcessingRequest`;
  - the base class is tested with stub middlewares (hook order, early response, all response hooks run, exceptions propagate);
  - `intParam()` is tested against the edge-case table.
- **Integration factory test:** builds every handler and every middleware registered in `config/routes.php` with the real test container.

### Performance and security

- Negligible cost: one handler plus a few middlewares are built per request, and services are lazy.
- Handler and middleware classes come only from route registration, never from the request.
- No per-request state in properties or statics, so nothing leaks between requests or tests.

### Hand-offs to sibling issues

- **#61 (middlewares):**
  - the middleware interface with `static build(Container): static`;
  - the `processRequest(ProcessingRequest): void` / `processResponse(ProcessingRequest, Response): Response` signatures used by `handle()`;
  - response hooks must be infallible in practice (headers only).
- **#62 (front controller):**
  - creates one `Container` per request;
  - the dispatcher builds the handler and the middlewares with `HandlerFactory`, concatenates the global and then the per-route middlewares, and calls `handle()`;
  - wraps the dispatch with `ErrorFormatter`.
- **Spec updates in this issue:**
  - `routing.md`: resolve the malformed-`:id` pending item (400) and update the #60 hand-off pointers;
  - `request-handler.md`: the index row;
  - `architecture/backend.md`: point "small container/factory class" to `Pidgey\Container`.

## Solution

Spec-only changes (no PHP, no tests):
- **Write `docs/agents/specs/request-handler/handlers.md`** with the decisions above. It starts with a title and a one-line `Purpose:` and follows `docs/agents/specs.md`. Sections:
  - base class and entry method;
  - factory and container;
  - lifecycle;
  - integer-id helper;
  - body validation;
  - exceptions;
  - edge cases;
  - example;
  - testing;
  - performance and security;
  - hand-offs.
- **Update `routing.md`:**
  - resolve the "status for a malformed `:id`" pending decision in favour of `400 BAD_REQUEST` (`BadRequestException('Validation failed (numeric string is expected)')` thrown by `intParam()`);
  - remove the pending item and fix the "Param values" bullets that say `NotFoundException`;
  - point the "decided in #60" hand-offs at the `handlers.md` sections.
- **Update `request-handler.md`:** adjust the `handlers.md` index row (base class, `HandlerFactory`, `Container` shape, lifecycle, `intParam()`, body validation, errors).
- **Update `architecture/backend.md`:** link "a small container/factory class" to `Pidgey\Container` in `handlers.md`.

## Benefits

- Gives the implementation issues one contract for building, wiring and running handlers.
- No global or static state, so handlers are testable with plain constructors and fakes.
- Resolves the 400-vs-404 conflict for a malformed `:id`, in line with the endpoint specs the frontend relies on.
- Every error goes through the single `ErrorFormatter`.
