# Plan: API specs: authentication model (access/refresh tokens, gate)

Issue: [27-api-specs-authentication-model-access-refresh-tokens-gate.md](../../issues/27-api-specs-authentication-model-access-refresh-tokens-gate.md)

## Overview
Rewrite the authentication spec following the Citadel contract and the decisions recorded in
issue #27. Add `architecture/configuration.md`, and fix every cross-reference that still
describes the PHP session or the `admin` Bearer scope. No code, migrations or `.env` changes.

## Context
- #25 (conventions) and #26 (data model: `users`, `refresh_tokens`, `api_tokens`) are merged.
  Their files already point to `api/authentication.md` for the rules.
- Decisions taken in #27:
  - disjoint credentials, and the `admin` scope is removed;
  - hand-rolled strict HS256;
  - bare env names, durations in seconds;
  - `SECRET_KEY` of at least 32 bytes;
  - the localhost secure-context exemption for dev;
  - Citadel's strict replay rule, including the multi-tab race;
  - PHP clock in UTC with no leeway.
- Citadel's `auth_users` / `auth_refresh_tokens` map to Pidgey's `users` / `refresh_tokens`.

## Steps

- [01 — Authentication spec folder](product-owner/01-authentication-folder.md)
- [02 — Configuration spec](product-owner/02-configuration.md)
- [03 — Cross-references and leftover wording](product-owner/03-cross-references.md)
- [04 — Architect-owned files](product-owner/04-architect-files.md)

## Notes
- **Step 04 touches architect-owned files** (`docs/agents/architecture.md`,
  `.claude/agents/security.md`). The architect applies it, not product-owner.
- The **security agent reviews** steps 01 and 02 before the PR is marked ready, focusing on:
  - the JWT rules and the gate;
  - rotation and replay;
  - cookie attributes and the disjoint credentials;
  - the `SECRET_KEY` rules.
- Pending decisions to record in the spec:
  - finer-grained Bearer scopes and token expiration;
  - cleanup of `refresh_tokens`;
  - refresh-token storage in the browser (XSS exposure, revisited with #32).
- Do not specify endpoint payloads or errors for `/auth/*` (#28), device authorization (#29),
  `/admin/users/*` (#30) or Pidgey admin endpoints (#31). Do not edit frontend specs other than the
  one link in `frontend/development.md` (#32).
- Every new spec file has a title, a `Purpose:` line and a **Pending decisions** section.
