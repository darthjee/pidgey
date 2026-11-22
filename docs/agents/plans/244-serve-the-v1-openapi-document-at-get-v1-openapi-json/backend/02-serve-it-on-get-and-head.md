# Serve it on GET and HEAD

Add `OpenApiHandler`, a `RequestHandler` that reads `source/openapi/v1.json` (path resolved from the source root, injectable for tests, following `StaticFileHandler`'s constructor/`build()` pattern) and returns a plain `Response` with the file's bytes, status `200` and `Content-Type: application/json`, with no cache headers. For a `HEAD` request it returns the same status and headers with an empty body. It touches no database.

Register both routes in `source/config/routes/v1.php` **without** per-route middleware:

```php
$router->get('/v1/openapi.json', OpenApiHandler::class);
$router->add('HEAD', '/v1/openapi.json', OpenApiHandler::class);
```

Update the file's doc comment: `/v1/openapi.json` is the one public `/v1` route.

Tests:
- unit: `GET` answers the file's bytes with the headers above, and `HEAD` answers the same headers with an empty body;
- integration (through the front controller): `GET` and `HEAD` answer `200` with no `Authorization`, with a valid Bearer and with an invalid Bearer, and no `last_used_at` is touched; `/v1/openapi.json/`, `/v1/openapi.jsonx`, `POST /v1/openapi.json` and `PUT /v1/openapi.json` get the routing `404 NOT_FOUND`; `POST /v1/messages` still answers `401` without a token;
- `RoutesSecurityTest`: `/v1/openapi.json` (`GET`, `HEAD`) is the only route under `/v1` with no `ApiTokenAuth`, and it never carries `Gate`.

## Files to Change
- `source/lib/Http/Handler/OpenApiHandler.php` — new handler.
- `source/config/routes/v1.php` — register `GET` and `HEAD /v1/openapi.json`.
- `source/tests/unit/Http/Handler/OpenApiHandlerTest.php` — new unit test.
- `source/tests/integration/Http/Handler/OpenApiHandlerTest.php` — new integration test.
- `source/tests/integration/Http/RoutesSecurityTest.php` — the public `/v1` exception.
- `source/tests/integration/Http/HandlerFactoryRoutesTest.php` — if it lists every route's handler.
