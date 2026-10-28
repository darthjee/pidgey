# Plan: Specs: request-handler — middlewares

Issue: [61-specs-request-handler-middlewares.md](../../issues/61-specs-request-handler-middlewares.md)

## Overview
Write `docs/agents/specs/request-handler/middlewares.md` from the decisions recorded in the issue's **Expected Behavior** section, then update the sibling specs that left hand-offs to #61. No PHP or tests.

## Context
- #55 Epic. #58 wrote `request-response/` (`ProcessingRequest` with attached data and the early-response contract, the `HttpException` family, `ErrorFormatter`). #59 wrote `routing.md` (`Router::add()` with middleware class names). #60 wrote `handlers.md` (`RequestHandler::handle()` runs the hooks, `HandlerFactory::middlewares()`, `static build(Container): static`).
- Guard behaviour already lives in `api/conventions/origin-check.md`, `api/authentication/gate.md` and `api/authentication/api-tokens.md`; guard order in `api/conventions/format.md#guard-order`.
- Format rules: `docs/agents/specs.md` (title, one-line `Purpose:`, **Pending decision** markers, English).

## Steps

- [01 — Write middlewares.md](product-owner/01-write-middlewares-spec.md)
- [02 — Resolve the hand-offs in request-response and handlers](product-owner/02-update-request-response-and-handlers.md)
- [03 — Update routing.md and the index](product-owner/03-update-routing-and-index.md)
- [04 — Verify](product-owner/04-verify.md)

## Notes
- The issue's Expected Behavior section is the source of truth. Don't re-decide anything. If something is unclear, record it as a **Pending decision** rather than inventing a rule.
- Link to the guard specs and the early-response contract; never redefine their behaviour.
- CORS and request logging are handed to #62; `front-controller.md` stays a stub, but `middlewares.md` records the hand-off.
- PHP snippets are short and illustrative only (the interface, `BaseMiddleware`, one `RequireScope` subclass, one route registration).
