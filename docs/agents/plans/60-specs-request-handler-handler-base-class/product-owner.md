# Plan: Specs: request-handler — handler base class

Issue: [60-specs-request-handler-handler-base-class.md](../../issues/60-specs-request-handler-handler-base-class.md)

## Overview
Write `docs/agents/specs/request-handler/handlers.md` from the decisions recorded in the issue's **Expected Behavior** section, then update `routing.md`, `request-handler.md` and `architecture/backend.md` to match. No PHP or tests.

## Context
- #55 Epic. #58 wrote `request-response/` (`ProcessingRequest`, `Response`, `HttpException` family, `ErrorFormatter`), and #59 wrote `routing.md` (`Router`, `RouteMatch`, hand-offs to #60).
- `routing.md` carries a **Pending decision** (status for a malformed `:id`) that this issue resolves in favour of `400 BAD_REQUEST`.
- Format rules: `docs/agents/specs.md` (title, one-line `Purpose:`, **Pending decision** markers, English).

## Steps

- [01 — Write handlers.md](product-owner/01-write-handlers-spec.md)
- [02 — Update routing.md](product-owner/02-update-routing.md)
- [03 — Index and backend architecture](product-owner/03-cross-links.md)
- [04 — Verify](product-owner/04-verify.md)

## Notes
- The issue's Expected Behavior section is the source of truth. Don't re-decide anything. If something is unclear, record it as a **Pending decision** rather than inventing a rule.
- Keep `handlers.md` structural. Endpoint behaviour stays in `api/endpoints/`, and the middleware contract stays with #61 (`middlewares.md` remains a stub).
- PHP snippets are short and illustrative only (the `handle()` sketch, the `HealthHandler` example, one handler with a `build()` override).
