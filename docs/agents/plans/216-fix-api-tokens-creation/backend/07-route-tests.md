# Route-level tests

Pin the new routes in the router-wide tests.

- `RoutesSecurityTest`: assert the three API-token routes carry `Gate` and no `AdminCheck` (follow how the SMTP connection routes are asserted).
- `HandlerFactoryRoutesTest`: assert `GET`/`POST /api-tokens.json` resolve to `ApiTokensHandler` and `DELETE /api-tokens/:id.json` to `ApiTokenHandler`.
- Run `make tests` (lint + full suite) and fix any PHPStan/PHPCS findings, including docblock requirements on the new classes.

## Files to Change
- `source/tests/integration/Http/RoutesSecurityTest.php` — new assertions.
- `source/tests/integration/Http/HandlerFactoryRoutesTest.php` — new assertions.
