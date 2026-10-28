# Plan: Specs: request-handler — routing

Issue: [59-specs-request-handler-routing.md](../../issues/59-specs-request-handler-routing.md)

## Overview
Write `docs/agents/specs/request-handler/routing.md` from the decisions recorded in the issue's **Expected Behavior** section. Convert API path params in the specs to the `:param` notation, then update the index and cross-links. No PHP, `.htaccess` or tests.

## Context
- #55 Epic. #56 created the stub, #57 wrote `rewrite.md`, and #58 wrote `request-response.md` plus `request-response/`.
- #58 already fixed `param(string $name): ?string` on the processing request, the `NotFoundException` in `request-response/errors.md`, and the 404 message `Cannot <METHOD> <path>` (no 405).
- Format rules: `docs/agents/specs.md` (title, one-line `Purpose:`, **Pending decision** markers, English).

## Steps

- [01 — Write routing.md](product-owner/01-write-routing-spec.md)
- [02 — Convert path-param notation](product-owner/02-param-notation.md)
- [03 — Cross-links and index](product-owner/03-cross-links.md)
- [04 — Verify](product-owner/04-verify.md)

## Notes
- The issue's Expected Behavior section is the source of truth. Don't re-decide anything. If something is unclear, record it as a **Pending decision** rather than inventing a rule.
- Keep `routing.md` structural. Endpoint behaviour stays in `api/endpoints/`.
