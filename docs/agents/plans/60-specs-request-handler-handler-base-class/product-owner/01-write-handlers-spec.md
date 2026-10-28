# Write handlers.md

Replace the stub in `docs/agents/specs/request-handler/handlers.md` with the full spec. Keep the title and the one-line `Purpose:` (adjust it to mention the factory, container shape, lifecycle, integer-id helper and errors). Add a "Part of the request flow indexed in [request-handler.md](../request-handler.md)" line, as in `routing.md`.

Sections, each taken from the issue's Expected Behavior:
- **`RequestHandler`:** the name, the final `handle(ProcessingRequest $request, array $middlewares): Response` with a short illustrative sketch, the protected abstract `process()`, errors propagating unchanged, and the hook signatures it uses (owned by #61). List the rejected alternatives.
- **Factory and container:**
  - `static build(Container): static`, with its `new static()` default and the override rule;
  - `Pidgey\Container`: root namespace, typed lazy memoized getters, one per request, getter list out of scope;
  - `HandlerFactory::handler()` / `middlewares()`;
  - how the dispatcher (#62) uses them;
  - laziness and failure;
  - the rejected alternatives.
- **Lifecycle:** one instance per dispatch, `private readonly` dependencies only, no static mutable state (the PHPUnit reason), container scope, no teardown hook, worker runtimes out of scope.
- **Route params and `intParam()`:**
  - handlers read params through `param()` (link `request-response/processing-request.md#route-params`);
  - `intParam(ProcessingRequest $r, string $name, string $notFoundMessage): int` as a protected method;
  - the outcomes table (wrong format → `400`, overflow → `404 $notFoundMessage`, missing param → `LogicException`, column range → the repository);
  - it is called first in `process()`.
  - Link the endpoint specs' `:id` sections.
- **Body validation:** runs in `process()` through a `Validator`, throws `ValidationFailedException`, and is not a middleware (guard order). The `Validator` API is left to implementation. Link `api/conventions/validation.md` and `api/conventions/format.md#guard-order`.
- **Errors:** throw `HttpException` subclasses and never build an `ErrorResponse`. Anything else becomes `500` through `ErrorFormatter` (link `request-response/errors.md`).
- **Edge cases:** wiring mistakes 1–4 and flow 5–9 from the issue, including "only request hooks use `respond()`" and "handlers commit their transactions inside `process()`".
- **Example:**
  - `HealthHandler` with an inherited `build()`, returning a `JsonResponse`, plus `$router->get('/health.json', HealthHandler::class)`;
  - a short handler with a `build(Container)` override that uses `intParam()` and throws `NotFoundException`.
- **Testing:** unit tests (construct with fakes, `handle($request, [])`, stub middlewares, the `intParam()` table) and the integration factory test.
- **Performance and security.**
- **Hand-offs:** to #61 (middleware interface with `build()`, hook signatures, response hooks infallible in practice) and to #62 (one container per request, the dispatcher, the `ErrorFormatter` wrapper).
- **Pending decisions:** "None" unless something unclear came up while writing.

## Files to Change
- `docs/agents/specs/request-handler/handlers.md` — replace the stub with the full spec.
