# Plan: Specs: request-handler — front controller (index.php)

Issue: [62-specs-request-handler-front-controller-index-php.md](../../issues/62-specs-request-handler-front-controller-index-php.md)

## Overview
Write `docs/agents/specs/request-handler/front-controller.md` from the decisions recorded in the issue's **Solution** section, then update the sibling specs that left hand-offs to #62. No PHP, `.htaccess` or tests.

## Context
- #55 Epic. #57 wrote `rewrite.md` (including `#transition`: `/` is no longer the health check). #58 wrote `request-response/` (`Request` with test overrides and the `%00` → `400`, `ErrorFormatter`, `ResponseSender`, `WallClock`). #59 wrote `routing.md` (`Router`, `match()`, `NotFoundException('Cannot <METHOD> <path>')`). #60 wrote `handlers.md` (`Container`, `HandlerFactory`, `handle()` catches nothing). #61 wrote `middlewares.md` (CORS and request logging are front-controller steps; global list = `OriginCheck`).
- CORS rules already live in `api/conventions/origin-check.md#trusted-origins-and-cors`; configuration in `architecture/configuration.md`; log redaction in `request-response/request.md` and `api/authentication.md`; `/health.json` in `api/endpoints/operational.md`.
- Format rules: `docs/agents/specs.md` (title, one-line `Purpose:`, **Pending decision** markers, English).

## Steps

- [01 — Write front-controller.md](product-owner/01-write-front-controller-spec.md)
- [02 — Resolve the global middleware list in middlewares.md and routing.md](product-owner/02-resolve-global-middleware-list.md)
- [03 — Resolve the hand-offs in handlers, request-response and the index](product-owner/03-update-hand-offs-and-index.md)
- [04 — Verify](product-owner/04-verify.md)

## Notes
- The issue's Solution section is the source of truth. Don't re-decide anything. If something is unclear, record it as a **Pending decision** rather than inventing a rule.
- Link to the CORS rules, the error envelope, the `Request`/`Response` models and the router; never redefine them.
- The log-level variable stays pending in `architecture/configuration.md`; production hosting (oak / mod_fcgid) is out of scope.
- PHP snippets are short and illustrative only (`index.php`, the `FrontController` outline, the `new Router([OriginCheck::class])` line in `config/routes.php`).
