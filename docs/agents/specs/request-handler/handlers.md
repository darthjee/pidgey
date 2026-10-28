# Request Handlers

Purpose: define the request handler base class (`RequestHandler`), the `HandlerFactory`, the shape of the `Pidgey\Container`, the handler lifecycle, the integer-id helper, where body validation runs, and how handlers report errors.

Part of the request flow indexed in [request-handler.md](../request-handler.md). The handler runs after routing ([routing.md](routing.md)) and the request hooks ([middlewares.md](middlewares.md)), and before the response hooks.

This file is structural only. What each endpoint does stays in [api/endpoints/](../api/endpoints/). The middleware interface stays in [middlewares.md](middlewares.md) (#61), and the front controller in [front-controller.md](front-controller.md) (#62). The processing request, the responses and the exceptions are used here, not redefined ([request-response.md](request-response.md)).

## `RequestHandler`

`Pidgey\Http\RequestHandler` is the **abstract** base class of every handler. It is not called `Handler`, which would read ambiguously next to Monolog's `*Handler` classes. `Router::add()` checks route handler classes against it with `is_subclass_of` ([routing.md](routing.md#router)).

### Entry method

**`final public function handle(ProcessingRequest $request, array $middlewares): Response`** (Tent-style template method):

1. runs the **request hooks** in order, and stops after the first hook that sets an early response (`$request->hasResponse()`, see [request-response/processing-request.md](request-response/processing-request.md#early-response));
2. uses the early response when one is set, otherwise calls `process()`;
3. runs **all response hooks**, in the same order, including after an early response. The last hook's return value is the response.

Illustrative sketch:

```php
final public function handle(ProcessingRequest $request, array $middlewares): Response
{
    foreach ($middlewares as $middleware) {
        $middleware->processRequest($request);
        if ($request->hasResponse()) {
            break;
        }
    }

    $response = $request->response() ?? $this->process($request);

    foreach ($middlewares as $middleware) {
        $response = $middleware->processResponse($request, $response);
    }

    return $response;
}
```

- **`$middlewares`** is the already-built, already-merged list: global middlewares first, then the route's, in guard order ([api/conventions/format.md](../api/conventions/format.md#guard-order)). The factory and the dispatcher build and merge it ([Factory and container](#factory-and-container)). The handler never knows which hooks are global.
- The list is an **argument, not a property**, so the handler holds no per-request state. Tests pass `[]`.

### Processing method

**`abstract protected function process(ProcessingRequest $request): Response`** holds the handler's logic. This fixes Tent's `processsRequest` typo. It is protected, so `handle()` is the only public surface.

### Errors propagate

`handle()` **catches nothing**. An exception from a request hook, from `process()` or from a response hook propagates unchanged to the error wrapper in the front controller ([front-controller.md](front-controller.md), #62). Whether error responses pass through the response hooks stays with #61 (the CORS hand-off in [request-response/processing-request.md](request-response/processing-request.md#only-for-non-error-answers)).

### Hook signatures

The hook signatures are owned by #61 ([middlewares.md](middlewares.md)). `handle()` only uses:

- `processRequest(ProcessingRequest $request): void`;
- `processResponse(ProcessingRequest $request, Response $response): Response`.

If #61 changes them, this spec follows.

### Rejected

- **A separate `Pipeline` that runs the hooks:** the final entry method would be left empty, which departs from the Tent-style decision in #55.
- **Tent's `prependMiddlewares()` / `buildMiddlewares()` setters:** they mutate the handler after construction.

## Factory and container

### `build(Container)`

`RequestHandler` declares **`public static function build(Container $container): static`**, which defaults to `return new static();`.

- A handler with dependencies **overrides** it, e.g. `return new self($c->smtpConnectionService(), $c->logger());`.
- Its constructor stays fully typed. The container is only touched in `build()`.
- A subclass that adds constructor arguments **must** override `build()`. The integration factory test catches a missing override ([Testing](#testing)).

**Middlewares** use the same `static build(Container): static` convention. The middleware interface itself belongs to #61 ([middlewares.md](middlewares.md)).

### `Pidgey\Container`

The "small container/factory class" of [architecture/backend.md](../architecture/backend.md).

- **Namespace:** the root namespace (`Pidgey\Container`), as the composition root. It builds the HTTP, services and infra layers, so it belongs to none of them. Putting it in `Pidgey\Infra\` would invert "dependencies flow downward".
- **Explicit typed getters**, lazy and memoized, e.g. `pdo(): PDO`, `logger(): LoggerInterface`, `wallClock(): WallClock`. Each service is built on its first call and reused for the rest of the request.
- **One `Container` per request**, created by the front controller (#62, see [Lifecycle](#lifecycle)).
- **Out of scope:** the list of getters. #62 creates the container, and each service's implementation issue adds its getter.

### `Pidgey\Http\HandlerFactory`

Built with the `Container`:

- **`handler(string $class): RequestHandler`** calls `$class::build($container)`;
- **`middlewares(list<class-string> $classes): list<Middleware>`** builds each class in order. It checks that each class is a middleware and throws `LogicException` otherwise ([Edge cases](#edge-cases)).

The dispatcher (#62, [front-controller.md](front-controller.md)):

1. calls `handler()` with the `RouteMatch`'s handler class ([routing.md](routing.md#dispatch-api));
2. calls `middlewares()` for the global middlewares and for the route's middleware class names, and concatenates them, global first;
3. calls `handle()` with the processing request and that list.

### Laziness and failure

- Only the matched route's handler and middlewares are built.
- The container opens nothing until a getter is called, so `/health.json` never touches the database ([api/endpoints/operational.md](../api/endpoints/operational.md)).
- A failure while building (e.g. the database is unreachable) is an ordinary exception, which the error wrapper turns into a `500`.

### Rejected

- **Reflection autowiring:** a DI framework in disguise.
- **A central class-to-closure map:** one shared file edited for every handler.
- **A generic `get(string $id): mixed` service locator:** untyped.
- **`psr/container`:** a new runtime dependency ([architecture/dependencies.md](../architecture/dependencies.md)).

## Lifecycle

- **One instance per dispatch:**
  - the factory builds the handler and its middlewares after the route matches;
  - they serve that one request and are then discarded;
  - this is unlike Tent, which reuses one handler per rule for every request that matches the rule.
- **State a handler may hold:** only injected dependencies, as `private readonly` constructor-promoted properties.
  - Per-request data (the request, params, the attached user, the parsed body) arrives through the `$request` argument and stays in local variables.
  - This is a convention checked in review.
- **No static mutable state** in handlers, middlewares or services. `static` is used only for `build()` and constants.
  - The reason is not production: under mod_fcgid, `php-cgi` processes persist, but PHP still resets statics on every request.
  - The reason is PHPUnit, which runs the whole suite in one process, so statics would leak between tests. This is the same reasoning as "no `reset()`" in [routing.md](routing.md#router).
- **Container scope:** one `Container` per request, created by the front controller (#62).
  - Memoized services are shared within that request (e.g. one PDO connection for the gate's session lookup and the handler's queries).
  - Nothing survives into the next request.
- **No teardown hook:** resources (PDO, SMTP sockets) close at the end of the request. The SMTP transport's own cleanup stays with the email specs ([email.md](../email.md)).
- **Worker runtimes** (FrankenPHP worker mode, RoadRunner, Swoole) are out of scope. The per-instance and no-static rules would already hold there; only the container scope would need revisiting.

## Route params and `intParam()`

Handlers read route params through `param()` on the processing request ([request-response/processing-request.md](request-response/processing-request.md#route-params)). They never re-parse the path. The router does not check a value's shape ([routing.md](routing.md#param-values)).

Integer ids are read with a **protected method on `RequestHandler`**:

**`protected function intParam(ProcessingRequest $r, string $name, string $notFoundMessage): int`**

| Value of the param | Outcome |
| --- | --- |
| Matches `^[1-9][0-9]*$` and fits in PHP's `int` | Returns the integer. |
| Wrong format (`0`, `01`, `-1`, `1.5`, `abc`, an empty string) | Throws `BadRequestException('Validation failed (numeric string is expected)')`: `400 BAD_REQUEST`. |
| Well formed but larger than `PHP_INT_MAX` | Throws `NotFoundException($notFoundMessage)` (e.g. `SMTP connection not found`): `404 NOT_FOUND`. |
| Missing: the route has no such param (`param()` returns `null`) | Throws `LogicException`: a route or handler bug, not a client error, so `500`. |
| Within PHP's `int` but beyond the column's range | Not the helper's job. The repository's `find()` returns `null`, which is the handler's ordinary `404`. |

- Handlers call `intParam()` **first in `process()`**, so the format check runs after the guards (the request hooks) and before body validation, as the endpoint specs require ([api/endpoints/pidgey-admin.md](../api/endpoints/pidgey-admin.md#id-path-parameter), [api/endpoints/admin-users.md](../api/endpoints/admin-users.md#id-path-parameter)).
- This resolves the former pending item in [routing.md](routing.md#param-values) ("status for a malformed `:id`") in favour of **`400 BAD_REQUEST`**.
- **Rejected:** returning `?int` for an overflowing value, which adds a null check to every handler.

## Body validation

- Body validation runs **inside `process()`**, through a `Validator` the handler calls. It is **not a middleware**, so the guards (the request hooks) always run first: step 4 of the [guard order](../api/conventions/format.md#guard-order).
- It collects every field error and throws `ValidationFailedException(list<string> $details)` ([request-response/errors.md](request-response/errors.md#exceptions)).
- Unknown fields are dropped, and messages follow [api/conventions/validation.md](../api/conventions/validation.md).
- The `Validator` API and its rule set are left to the implementation issue. There is no separate spec: [api/conventions/validation.md](../api/conventions/validation.md) already fixes the observable behaviour.

## Errors

- Handlers report every error by **throwing**: an `HttpException` subclass, `ValidationFailedException`, or `HttpException` with an SMTP code ([request-response/errors.md](request-response/errors.md#exceptions)).
- They **never build an `ErrorResponse`** themselves, unlike Tent's `StaticFileHandler`, which maps exceptions to responses.
- Any other `Throwable` becomes `500 INTERNAL_ERROR` through the single `ErrorFormatter` ([request-response/errors.md](request-response/errors.md#errorformatter)), called by the front controller (#62).

## Edge cases

**Wiring mistakes** are programmer errors. Each one throws `LogicException` (or another `Throwable`), which becomes a `500`:

1. **A route's handler class does not extend `RequestHandler`:** `Router::add()` rejects it ([routing.md](routing.md#router), #59).
2. **A route's middleware class is not a middleware:** `HandlerFactory::middlewares()` checks each class and throws `LogicException`. The integration factory test builds every route's middlewares as well as its handler.
3. **A handler adds constructor arguments without overriding `build()`:** `new static()` throws an `ArgumentCountError`, which becomes a `500`. The factory test catches it.
4. **`intParam('id')` on a route with no `:id`:** `param()` returns `null`, and the helper throws `LogicException` (a route or handler bug, not a client error).

**Flow:**

5. **A request hook answers early:** `process()` is not called, and all response hooks run.
6. **A request hook calls `respond()` and then throws:** the exception wins and propagates. The early response is discarded.
7. **`process()` calls `$request->respond()`:** not allowed, but not guarded. `handle()` uses only `process()`'s return value. Rule: **handlers return their response, and only request hooks use `respond()`**.
8. **A response hook throws after `process()` committed a side effect:** the client gets a `500` although the action happened.
   - **Handlers own their transactions and commit inside `process()`.**
   - Response hooks must be infallible in practice (headers only). This is a constraint handed to #61.
9. **A response hook replaces the response:** allowed. The last hook's return value is sent.

## Example

Illustrative only.

`HealthHandler` has no dependencies, so `build()` is inherited:

```php
namespace Pidgey\Http\Handler;

final class HealthHandler extends RequestHandler
{
    protected function process(ProcessingRequest $request): Response
    {
        return new JsonResponse(['status' => 'ok']);
    }
}
```

```php
$router->get('/health.json', HealthHandler::class);
```

A handler with a dependency overrides `build()`, reads its id with `intParam()` and throws `NotFoundException`:

```php
final class ShowSmtpConnectionHandler extends RequestHandler
{
    public function __construct(
        private readonly SmtpConnectionService $connections,
    ) {
    }

    public static function build(Container $c): static
    {
        return new self($c->smtpConnectionService());
    }

    protected function process(ProcessingRequest $request): Response
    {
        $id = $this->intParam($request, 'id', 'SMTP connection not found');
        $caller = $request->require(Caller::class);

        $connection = $this->connections->find($caller->organizationId(), $id)
            ?? throw new NotFoundException('SMTP connection not found');

        return new JsonResponse($connection);
    }
}
```

The namespaces, the service name and the `Caller` interface above are illustrative; the endpoint's behaviour is defined in [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md).

## Testing

- **Unit:**
  - handlers are constructed directly with fakes (`new X($fake)`), bypassing `build()`, and exercised through `handle($request, [])` with a hand-built `ProcessingRequest`;
  - the base class is tested with stub middlewares: hook order, early response, all response hooks run, exceptions propagate;
  - `intParam()` is tested against the [outcomes table](#route-params-and-intparam).
- **Integration factory test:** builds every handler and every middleware registered in `config/routes.php` with the real test container. It catches a missing `build()` override and a non-middleware class.

## Performance and security

- Negligible cost: one handler plus a few middlewares are built per request, and services are lazy.
- Handler and middleware classes come only from route registration, never from the request.
- No per-request state in properties or statics, so nothing leaks between requests or tests.

## Hand-offs

- **#61 (middlewares, [middlewares.md](middlewares.md)):**
  - the middleware interface, with `static build(Container): static`;
  - the `processRequest(ProcessingRequest): void` / `processResponse(ProcessingRequest, Response): Response` signatures used by `handle()`;
  - response hooks must be infallible in practice (headers only).
- **#62 (front controller, [front-controller.md](front-controller.md)):**
  - creates one `Container` per request;
  - the dispatcher builds the handler and the middlewares with `HandlerFactory`, concatenates the global and then the per-route middlewares, and calls `handle()`;
  - wraps the dispatch with `ErrorFormatter`.

## Pending decisions

None.
