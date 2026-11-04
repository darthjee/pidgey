# Routes, allowlist and log checks
Wire the handlers and prove the deny-by-default and no-secret-in-logs rules still hold.

- `source/config/routes/auth.php`: add `$router->post('/auth/refresh.json', RefreshHandler::class)`, `$router->delete('/auth/logoff.json', LogoffHandler::class)` and `$router->post('/auth/status.json', StatusHandler::class)`, with no route middlewares. Update the file docblock: it now covers every public session route, all ungated and behind the global `OriginCheck`.
- `RoutesSecurityTest::publicRoutes()`: add `'POST /auth/refresh.json'`, `'DELETE /auth/logoff.json'` and `'POST /auth/status.json'`, matching how register and login were added in #131. Check how `RouteGuards` decides a route is public and update its allowlist too if it is kept there.
- `HandlerFactoryRoutesTest`: make sure the three new handlers build from the real `Container`. It may already iterate every route; if it lists them, add them.
- `AuthLogTest`:
  - a cross-site `POST /auth/refresh.json`, `DELETE /auth/logoff.json` and `POST /auth/status.json` are rejected with `403` by `OriginCheck` before the handler, and no token row changes;
  - a same-origin flow end to end: register, refresh, status, logoff. A refresh after the logoff then answers `401`;
  - extend `testFailuresLogNoSecret` (or add a sibling test) with failing and succeeding refresh, logoff and status requests, and assert that no captured log line contains the presented or the new refresh token, the access token JWT, or a token hash.

## Files to Change
- `source/config/routes/auth.php`: three routes plus the docblock.
- `source/tests/integration/Http/RoutesSecurityTest.php`: allowlist entries.
- `source/tests/Support/RouteGuards.php`: only if the public allowlist lives there.
- `source/tests/integration/Http/HandlerFactoryRoutesTest.php`: the new handlers, if it enumerates them.
- `source/tests/integration/Http/AuthLogTest.php`: origin checks, an end-to-end flow, and the log assertions.
