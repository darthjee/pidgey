# Product-owner Plan: Specs: static-file serving of the frontend at `/`

Main plan: [plan.md](plan.md)

## Overview

Rewrite every spec that describes the UI at `/admin/` and add a spec for the static-file handler, so #122 (Router), #123 (handler and routes) and #124 (build/deploy move) can implement against them. No code, no non-spec docs (those move with #124).

## Context

Decisions come from epic #120 and the enhancement of #121 (see the issue file): build in `source/static/` outside the web root; Vite `base: '/'`; routes in order `GET /` → `index.html`, `GET /assets/*` → static, API routes, then an extensionless-GET fallback → `302 /#<raw REQUEST_URI>` (outside `/v1/`); `/admin/` dropped; dev keeps Vite's own proxy.

## Steps

- [01 — New static-files spec](product-owner/01-static-files-spec.md)
- [02 — Routing, rewrite and entry-point specs](product-owner/02-routing-rewrite-entry-point.md)
- [03 — Frontend specs](product-owner/03-frontend-specs.md)
- [04 — Overview, layout and Docker specs](product-owner/04-overview-layout-docker.md)
- [05 — Verify](product-owner/05-verify.md)

## CI Checks

None: CI has no docs job. Verification is the grep in step 05.

## Notes

- Specs only. `docs/agents/architecture.md`, `docs/agents/folder-structure.md`, `.claude/agents/backend.md` and `docs/agents/deployment/*.md` are updated by #124.
- Do not resolve the existing CSP pending decision in `frontend/screens.md`; only re-point it from `/admin/` to `index.html` at `/` and note `frame-ancestors 'none'` is decided.
- Follow the spec granularity rule (AGENTS.md): `static-files.md` is a detail file indexed from `request-handler.md`.
