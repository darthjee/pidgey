# Backend Plan: RequestHandler base class and status handler

Main plan: [plan.md](plan.md)

## Overview

Replace the placeholder `source/public/index.php` with a thin front controller that builds a `ProcessingRequest`, runs `HealthHandler` through `RequestHandler::handle()` with no middlewares, and sends the result with `ResponseSender`. Every request, whatever its method or path, answers `200`, `Content-Type: application/json`, `{"status":"ok"}`.

## Context

The specs already define everything built here. Build **only** the members listed in the steps; the rest of each spec comes in later issues:

- `docs/agents/specs/request-handler/request-response/request.md`
- `docs/agents/specs/request-handler/request-response/processing-request.md`
- `docs/agents/specs/request-handler/request-response/response.md`
- `docs/agents/specs/request-handler/middlewares.md`
- `docs/agents/specs/request-handler/handlers.md`

These pieces are out of scope:

- `build(Container)` on handlers and middlewares, `Pidgey\Container` and `HandlerFactory`;
- the router;
- concrete middlewares;
- logging, the error wrapper and CORS;
- `NoContentResponse` and `SetCookie`;
- cookies, the body and attachments on the request.

Code conventions follow the existing `source/lib/Infra/` classes:

- `declare(strict_types=1)`;
- `final` classes where possible;
- a docblock on every public method (phpcs `Squiz.Commenting.FunctionComment` applies to `lib/`, not to `tests/`);
- PSR-12 plus slevomat.

Tests follow `source/tests/unit/Infra/*Test.php`:

- namespace `Pidgey\Tests\Unit\...`, mirroring `lib/`;
- the `#[CoversClass(...)]` attribute;
- `final class ...Test extends TestCase`.

## Steps

- [01 — Request models](backend/01-request-models.md)
- [02 — Responses and ResponseSender](backend/02-responses-and-sender.md)
- [03 — Middleware types and RequestHandler](backend/03-middleware-and-request-handler.md)
- [04 — HealthHandler and index.php](backend/04-health-handler-and-index.md)

## CI Checks

- `source/`: `docker compose run --rm tests composer tests:unit` and `docker compose run --rm tests composer lint` (CI jobs: `backend_tests`, which runs `composer coverage`, and `backend_checks`, which runs `composer lint`).
- Per #73, `backend_checks`/`backend_tests` already fail on `main` for unrelated reasons. Don't fix them here or edit `.circleci/config.yml`. The new code itself must pass `tests:unit` and `lint` locally.

## Notes

- `index.php` is under `public/`, outside the coverage `<source>` (`lib/` only). Keep it a few lines long; it is tested manually.
- `Request::path()` doesn't reject a decoded NUL byte yet. Answering `400` for it needs `BadRequestException` and the error wrapper, which come in later issues; say in the class docblock that the NUL check is pending.
- Headers, cookies and the body are left off `Request`. Nothing in this issue reads them, and the spec's header normalization deserves its own tests when a guard needs it.
- When the router arrives, `HealthHandler` is bound to `GET /health.json` unchanged.
