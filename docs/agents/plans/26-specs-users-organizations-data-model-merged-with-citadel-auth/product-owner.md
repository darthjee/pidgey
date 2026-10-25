# Product Owner Plan: Specs: users & organizations data model (merged with Citadel auth)

Main plan: [plan.md](plan.md)

## Overview
Rewrite `organizations/entities.md`, split `organizations/data-model.md` into a
`data-model/` folder with the new `users` columns and the four auth tables, rewrite
`organizations/bootstrap.md` (registration, initial status, Login section replaced by a
pointer), adjust `organizations/scope.md` and `overview/terminology.md`, and fix the
cross-references. No code, migrations or frontend changes.

## Context
- The issue file holds every decision and the verbatim Citadel table excerpts (section
  "Reference: Citadel contract"); adapt them: plain table names (`users`,
  `refresh_tokens`, `password_reset_tokens`, `authorization_requests`,
  `account_edit_lockouts`), `BIGINT` ids and `*_id` columns, physical `FOREIGN KEY`
  constraints, no Citadel source links, no `CITADEL_PLACEHOLDER_` prefix, no `demo` seed.
- Token mechanics (JWT cookie, refresh rotation, replay detection, gate behavior) belong to
  #27 and `api/authentication.md`: describe columns with a one-line meaning and link there.
- Endpoint payloads and error codes belong to #28–#31; frontend changes to #32.
- Every spec file keeps: title, `Purpose:` line, content, **Pending decisions**
  (granularity rule in `docs/agents/specs.md`).

## Steps

- [01 — Entities](product-owner/01-entities.md)
- [02 — Data model](product-owner/02-data-model.md)
- [03 — Bootstrap and registration](product-owner/03-bootstrap.md)
- [04 — Scope and terminology](product-owner/04-scope-and-terminology.md)
- [05 — Cross-references](product-owner/05-cross-references.md)

## CI Checks
- None specific to docs; keep Markdown links valid (relative paths).

## Notes
- The security agent should review step 02 (token hashes, lockout counters, unique
  constraints) and step 01/04 (the admin's reach limited to user management).
- `api/authentication.md` still describes the PHP session until #27; do not edit it here.
- Pending decisions to record (not resolve): joining an existing organization; whether a deny
  revokes the organization's API tokens; how the first admin is created (future issue).
