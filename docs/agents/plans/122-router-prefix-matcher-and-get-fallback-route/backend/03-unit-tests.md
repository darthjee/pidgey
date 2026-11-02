# Unit tests
Cover every case listed in the issue and in `routing.md#testing`, using `new Request(['method' => ..., 'uri' => ...])`, `StubHandler` and the existing helpers.

**`RouteTest`:**
- `/assets/*` compiles to `#^/assets/.*$#`;
- it matches `/assets/x.js` and `/assets/fonts/x.woff2`;
- it does not match `/assets`, `/assetsX` or `/Assets/x.js`;
- a wrong method gives `null`;
- `*` placement errors throw `LogicException` (data provider: `/a*`, `/a/*/b`, `/*x`);
- `normalizedPattern()` keeps the `*`.

**`RouterTest`, prefix routes:**
- registering `GET /assets/*` twice throws;
- a prefix route takes part in first-match-wins order.

**`RouterTest`, fallback:**
- data provider, matched (fallback handler returned): `/foo`, `/foo/`, `/users/42`, `/foo.bar/baz`, `/v1`, `/assets`, `/assetsX`;
- data provider, not matched (`null`): `GET /x.json`, `GET /v1/`, `GET /v1/messages`, `GET /favicon.ico`, `GET /robots.txt`, `GET /index.html`, `GET /index.php`, `POST /foo`, `HEAD /foo`;
- an ordinary route registered **after** `fallback()` still wins for its own path;
- the `RouteMatch` has no params and no middlewares;
- without a registered fallback, unmatched extensionless GET still gives `null`;
- a second `fallback()` throws `LogicException`;
- a non-`RequestHandler` class throws `LogicException`.

**`FrontControllerTest`:** add one case: a router with a fallback still answers `POST /foo` and `GET /x.json` with the JSON 404 `Cannot <METHOD> <path>`. The existing 404 tests stay unchanged.

## Files to Change
- `source/tests/unit/Http/RouteTest.php` — prefix compilation, matching and validation tests.
- `source/tests/unit/Http/RouterTest.php` — prefix duplicate/order tests and fallback tests.
- `source/tests/unit/Http/FrontControllerTest.php` — a fallback-router 404 case.
