# Deny-by-default and factory integration tests
Add the two integration tests required by `middlewares.md#testing` and `handlers.md#testing`.

- **`Router::routes(): array`:** returns the registered `Route`s, plus the fallback if one is registered, so the tests can enumerate them.
  - Add accessors to `Route` for its handler class and its middlewares, if missing.
  - Keep `Pidgey\Http\` free of auth classes.
- **Deny-by-default test** (`tests/integration/Http/RoutesSecurityTest.php` or similar). It loads `config/routes.php` and checks:
  - every route outside an explicit public allowlist lists `Gate::class` or `ApiTokenAuth`. The allowlist is `GET /`, `GET /assets/*`, `GET /health.json` and the GET fallback.
  - `ApiTokenAuth` does not exist yet, so reference it by its future FQCN string `Pidgey\Auth\ApiTokenAuth`.
  - `AdminCheck` appears only after `Gate` in the same list.
  - Add a negative test: a hand-built router with an unguarded non-public route, or `AdminCheck` without `Gate`, fails the checker. Extract the check into a small helper so it can be tested.
- **Factory test:** builds every route's handler and middlewares (global plus route) through `HandlerFactory` with a real test `Container`. This catches a missing `build()` override and a non-middleware class.

## Files to Change
- `source/lib/Http/Router.php` — `routes()`
- `source/lib/Http/Route.php` — accessors if missing
- `source/tests/integration/Http/RoutesSecurityTest.php` — new
- `source/tests/integration/Http/HandlerFactoryRoutesTest.php` — new
- `source/tests/unit/Http/RouterTest.php` — cover `routes()`
