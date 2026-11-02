# build(Container), HandlerFactory and per-request Container
Bring the dispatcher in line with `handlers.md` and `front-controller.md` ("Build the handler and the middlewares with the HandlerFactory", "One Container per request").

- **`Middleware` interface:** add `public static function build(Container $container): static`.
- **`BaseMiddleware`:** implement it as `return new static();`.
- **`RequestHandler`:** add the same `build()` with the same default.
- **Classes with constructor arguments override `build()`:**
  - `OriginCheck` (optional `TrustedOrigins`);
  - `StaticFileHandler` (optional `baseDir` and logger);
  - test support classes with required constructor arguments, such as `RecordingMiddleware`.

  Where all arguments are optional, the default `new static()` already works; override only if a dependency should come from the container.
- **New `Pidgey\Http\HandlerFactory`:**
  - `__construct(private readonly Container $container)`;
  - `handler(string $class): RequestHandler` checks that `$class` is a `RequestHandler` subclass (otherwise `LogicException`), then calls `$class::build($this->container)`;
  - `middlewares(array $classes): array` does the same per class against `Middleware` (otherwise `LogicException`) and keeps the order.
- **`FrontController`:**
  - inject a factory closure or a `Container` source (e.g. `?Closure $containerFactory`, defaulting to `fn () => new Container()`), so each `handle()` call builds **one fresh `Container`** and one `HandlerFactory`;
  - build the handler and `[...globalMiddlewares(), ...$match->middlewares]` through the factory;
  - container construction must stay cheap and side-effect free: no PDO;
  - update the class docblock, which currently says the HandlerFactory and Container "plug in later".
- **`Container`:** add lazy, memoized getters:
  - `wallClock(): WallClock`, returning `SystemWallClock`;
  - `accessToken(): AccessToken`, built from `SecretKeys::fromEnv()`, `ACCESS_TOKEN_TTL` (the same default as `ConfigValidator`, `900`; expose the default as a public constant there rather than duplicating it) and `wallClock()`.

  Allow tests to override the clock, for example through an optional constructor argument, following the existing `ConnectionFactory` injection style.

Tests:
- `HandlerFactoryTest`:
  - builds a handler and middlewares in order;
  - rejects a non-handler or non-middleware class with `LogicException`;
  - a `build()` override receives the container.
- `FrontControllerTest`: dispatch still works.
- A test that `GET /health.json` through `FrontController` opens no PDO connection: use a `ConnectionFactory` fake that fails the test, or counts calls, when it is used.
- `ContainerTest`: `wallClock()` and `accessToken()` are memoized.

## Files to Change
- `source/lib/Http/Middleware.php` — add `build()`
- `source/lib/Http/BaseMiddleware.php` — default `build()`
- `source/lib/Http/RequestHandler.php` — default `build()`
- `source/lib/Http/HandlerFactory.php` — new
- `source/lib/Http/FrontController.php` — per-request Container and HandlerFactory
- `source/lib/Http/OriginCheck.php`, `source/lib/Http/Handler/StaticFileHandler.php` — `build()` overrides if needed
- `source/lib/Container.php` — `wallClock()`, `accessToken()`
- `source/lib/Infra/ConfigValidator.php` — expose the `ACCESS_TOKEN_TTL` default as a public constant
- `source/tests/support/*Middleware.php`, `source/tests/support/*Handler.php` — `build()` overrides where constructors need arguments
- `source/tests/unit/Http/HandlerFactoryTest.php` — new
- `source/tests/unit/Http/FrontControllerTest.php`, `source/tests/unit/ContainerTest.php` — extend
