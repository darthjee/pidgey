# Product Owner Plan: Specs: request-handler — Request and Response models

Main plan: [plan.md](plan.md)

## Overview

Write the spec for Pidgey's HTTP models under `docs/agents/specs/request-handler/`, using the issue's Solution as the source of every decision. The content is about 250 lines, too much for one coherent file. Following the spec granularity rule in `docs/agents/specs.md`, `request-response.md` becomes a short index of a `request-response/` folder. Its path stays the same, so no inbound link changes.

## Context

- The stub `docs/agents/specs/request-handler/request-response.md` was created by #56. It holds the title, the `Purpose:` line and the `**Pending decision:** content to be specified in #58.` marker.
- The sibling `rewrite.md` (#57, merged) shows the expected style: a title, a `Purpose:` line, a "Part of the request flow" line, and short sections that explain *why*.
- The index sync rules (comment on the issue) say:
  - the PR fills the stub;
  - the PR keeps the `request-handler.md` table row and diagram in sync;
  - a split into a folder updates the index table in the same PR.
- The issue's Hand-offs (#59, #61, #62, sessions) are **not** written into those siblings here. Their stubs still belong to their own issues. Only the links listed in step 02 are added.

## Steps

- [01 — Write the request-response spec](product-owner/01-write-spec.md)
- [02 — Cross-links and index sync](product-owner/02-cross-links.md)
- [03 — Verify](product-owner/03-verify.md)

## Notes

- Decisions must be copied from the issue's Solution, not re-derived. In particular:
  - `header($name, $default)`;
  - `instanceof` lookup for attached data;
  - all response hooks run after an early answer, in Tent's registration order;
  - a 1 MiB limit, with an empty body read as `[]` for any `Content-Type`;
  - `WallClock` as a new interface, separate from the monotonic `Clock`.
- Record the rejected alternatives briefly, as the issue does, so later readers see why.
- The facts checked on `php:8.4-apache` (header mapping, cookie parsing) go in as documented behaviour, marked as checked.
