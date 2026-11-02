# 03 — HashRedirectHandler and frontend routes

## HashRedirectHandler
Create `final class HashRedirectHandler extends RequestHandler` in `Pidgey\Http\Handler`:
- `process()` returns `new Response('', 302, ['Location: /#' . $request->uri()])`;
- the URI is never decoded or normalised;
- the docblock explains why that is safe: it always starts with `/#`, so there is no open redirect, and the raw URI keeps CR/LF percent-encoded, while `addHeader()` rejects CR/LF anyway.

## Routes
- New `source/config/routes/frontend.php`, in the same style as `operational.php`:
  ```php
  $router->get('/', StaticFileHandler::class);
  $router->get('/assets/*', StaticFileHandler::class);
  ```
- `source/config/routes.php`:
  - require `routes/frontend.php` **before** `routes/operational.php`;
  - after every API route file, call `$router->fallback(HashRedirectHandler::class);`;
  - update the file comment to describe the order (frontend, API, fallback last).

## Tests
- Add `source/tests/unit/Http/Handler/HashRedirectHandlerTest.php`:
  - `/foo?x=1` → `/#/foo?x=1`;
  - `/foo/` → `/#/foo/`;
  - `/a%0D%0Ab` keeps `%0D%0A`;
  - `//evil.com` → `/#//evil.com`;
  - the status is 302 and the body is empty.
- `source/tests/unit/Config/RoutesTest.php`:
  - add endpoint rows for `GET /` → `StaticFileHandler` with pattern `/`, and `GET /assets/x.js` → `StaticFileHandler` with pattern `/assets/*`;
    - the existing data-provider test asserts `pattern === path`, so either add a separate test or add a pattern column to the provider;
  - `GET /foo` and `GET /v1` match `HashRedirectHandler`;
  - none of these match (null): `GET /nope.json`, `GET /favicon.ico`, `GET /index.php`, `GET /v1/messages`, `HEAD /` and `POST /foo`;
  - replace `testUnknownPathsDoNotMatch` (it asserted `GET /` → null) with this data-provider version;
  - a frontend-first test: `GET /` resolves to `StaticFileHandler` even though the fallback exists.
- Optional end-to-end: a `FrontControllerTest` case where `GET /nope.json` gives the JSON 404 `Cannot GET /nope.json` through the real routes table. Only add it if it fits the existing test setup.

## Files to Change
- `source/lib/Http/Handler/HashRedirectHandler.php` — new.
- `source/config/routes/frontend.php` — new.
- `source/config/routes.php` — require order and fallback registration.
- `source/tests/unit/Http/Handler/HashRedirectHandlerTest.php` — new.
- `source/tests/unit/Config/RoutesTest.php` — frontend and fallback assertions.
