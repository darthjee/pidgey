# Backend Plan: Router: prefix matcher and GET fallback route

Main plan: [plan.md](plan.md)

## Overview
Implement the two router capabilities fixed by `docs/agents/specs/request-handler/routing.md` (sections "Prefix routes", "GET fallback", "Testing"):
- a pattern ending in `/*` is a prefix route;
- `Router::fallback(string $handlerClass)` registers the single GET fallback, always tried last.

## Context
- `Route` compiles literals and `:param` segments (`[^/]+`) into an anchored regex, lazily.
- `Router::match()` returns the first matching `Route`'s `RouteMatch`, or `null`.
- `FrontController::handle()` turns `null` into `NotFoundException('Cannot <METHOD> <path>')`.
- That 404 path stays untouched: the fallback only changes which requests get `null`.

## Steps

- [01 — Prefix routes in Route](backend/01-prefix-routes.md)
- [02 — GET fallback in Router](backend/02-get-fallback.md)
- [03 — Unit tests](backend/03-unit-tests.md)

## CI Checks
- `source/`: `make tests` (runs `composer lint && composer tests` in the `tests` container; CI jobs: backend lint + tests)

## Notes
- **Out of scope:**
  - `routes/frontend.php`, `StaticFileHandler`, the redirect handler, and the routes-table (`tests/unit/Config/RoutesTest.php`) assertions about frontend routes, all in #123;
  - `RoutesTest::testUnknownPathsDoNotMatch` (`GET /` → `null`) stays valid until #123 registers `GET /`.
- **Coverage:** keep 100% on `Route` and `Router`. Every new branch needs a test: invalid `*`, second fallback, non-RequestHandler fallback, and each fallback-rule rejection.
