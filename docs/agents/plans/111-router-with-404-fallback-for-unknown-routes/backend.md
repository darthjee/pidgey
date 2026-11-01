# Backend Plan: Router with 404 fallback for unknown routes

Main plan: [plan.md](plan.md)

## Shared contracts

- Create `source/config/routes.php` (returns a `Router`) and `source/config/routes/operational.php`; this folder ships with the release (infra/product-owner document it). Do not put anything secret in it: `config/.env` is linked there at release time and is git-ignored.
- After release: `GET /health.json` → `200 {"status":"ok"}`, everything else → `404 NOT_FOUND` envelope `Cannot <METHOD> <path>`.

## Steps

- [01 — Errors: exceptions, ErrorResponse, WallClock, ErrorFormatter](backend/01-errors.md)
- [02 — Router, Route and RouteMatch](backend/02-router.md)
- [03 — Route params on ProcessingRequest](backend/03-route-params.md)
- [04 — Route files](backend/04-route-files.md)
- [05 — FrontController and index.php](backend/05-front-controller.md)

## CI Checks
- `source/`: `composer lint` (CI job: lint) and `composer tests:unit` / `composer coverage` (CI job: tests). Run them in the dev container (`make tests` or `docker compose run --rm tests …`).

## Notes
- Specs are the source of truth: `docs/agents/specs/request-handler/routing.md`, `front-controller.md`, `request-response/errors.md`, `request-response/processing-request.md`, `api/conventions/errors.md`.
- Out of scope (do not implement): `Cors`, `OriginCheck`, request logging/Monolog wiring, `HandlerFactory`, per-request `Container`, boot-time config validation, the last-resort hard-coded `500`, and the other route areas. Leave short comments where the spec's later pieces plug in only if they help the reader; don't add stubs.
- `monolog/monolog` is already a runtime dependency but no logger is wired yet; by decision, `ErrorFormatter` reports the real error with `error_log()` for now.
- Only the classes needed here: of the `HttpException` subclasses, add `NotFoundException` (others come with the endpoints that throw them).
