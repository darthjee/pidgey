# Routes, route tests and log check
- **`source/config/routes/auth.php`** (new): `$router->post('/auth/register.json', RegisterHandler::class);` and `$router->post('/auth/login.json', LoginHandler::class);`. No route middlewares: both are public, the global `OriginCheck` applies (cross-origin `POST`s are rejected before the handler).
- **`source/config/routes.php`**: `require __DIR__ . '/routes/auth.php';` after `operational.php`, before the fallback.
- **`RoutesSecurityTest::publicRoutes()`**: add `POST /auth/register.json` and `POST /auth/login.json` to the allowlist (they are deliberately ungated).
- **`HandlerFactoryRoutesTest`**: confirm both new handlers build with the real container (the data provider reads the routes table; add them if it lists classes by hand).
- **End-to-end** through `FrontController` (if the suite has such tests; otherwise at handler level): a cross-origin `POST /auth/login.json` gets the origin-check rejection; a same-origin one reaches the handler; `GET /auth/login.json` is not matched as a POST route.
- **Log check:** run a register and a login (success and failures) with a capturing logger (the one `ErrorFormatter`/`StaticFileHandler` use, swapped in the container or handler) and assert the captured output contains neither the plain password, the digest, the refresh token nor the JWT. Also force an unexpected error path if practical (e.g. a 500) and check the same.
- **Docs:** if any spec detail turns out wrong or ambiguous while implementing, fix the spec in the same PR (product-owner scope) rather than diverging silently.

## Files to Change
- `source/config/routes/auth.php` — new.
- `source/config/routes.php` — require it.
- `source/tests/integration/Http/RoutesSecurityTest.php` — allowlist.
- `source/tests/integration/Http/HandlerFactoryRoutesTest.php` — if needed.
- `source/tests/integration/Http/AuthLogTest.php` (or within the handler tests) — no secrets in logs.
