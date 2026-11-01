# Route files
Add the route files per `routing.md#route-files`.

- `source/config/routes.php`: `$router = new Router();` (empty global list for now; `OriginCheck` is added by its own issue), `require __DIR__ . '/routes/operational.php';`, `return $router;`.
- `source/config/routes/operational.php`: `$router->get('/health.json', HealthHandler::class);` (document `@var Router $router` for static analysis).
- Add `config` to the lint scope: `<file>config</file>` in `phpcs.xml` and `config` to the phpmd path list in the `lint`/`lint:fix` scripts of `composer.json`.
- Routes-table test: load `config/routes.php` and assert `GET /health.json` resolves to `HealthHandler`. Shadowing checks become meaningful once more routes exist; a simple "every route is reachable by its own literal path" assertion is enough now.

## Files to Change
- `source/config/routes.php`, `source/config/routes/operational.php` — new.
- `source/phpcs.xml`, `source/composer.json` — add `config` to the lint scope.
- `source/tests/unit/Config/RoutesTest.php` (or similar) — new routes-table test.
