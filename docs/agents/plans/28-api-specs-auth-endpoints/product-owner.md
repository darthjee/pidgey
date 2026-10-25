# Product Owner Plan: API specs: auth endpoints

Main plan: [plan.md](plan.md)

## Overview
Write the auth endpoint specs (#28 of epic #24) under `docs/agents/specs/api/endpoints/`,
following the Citadel contract excerpts in the issue file **as overridden by its numbered
decisions** (1–13). No PHP, migrations or frontend changes.

## Context
- The authentication model (#27) is merged: `api/authentication/` (`access-token.md`,
  `sessions.md`, `gate.md`, `api-tokens.md`, `local-development.md`) and
  `architecture/configuration.md` exist. Do not restate them; link to them.
- Data model (#26): `organizations/data-model/password-reset-tokens.md`,
  `account-edit-lockouts.md`, `refresh-tokens.md`, `organizations-and-users.md`.
- Conventions (#25): `api/conventions/{format,validation,errors,origin-check}.md`.
- Every spec file keeps the format of `docs/agents/specs.md`: title, `Purpose:` line,
  content, **Pending decisions** (or "None.").
- Citadel-only details are dropped: Citadel source links, the `CITADEL_PLACEHOLDER_` prefix,
  "bcrypt cost 10", millisecond settings, "asynchronously / fire-and-forget".

## Steps

- [01 — Endpoints index, sending and admin files](product-owner/01-endpoints-index.md)
- [02 — Auth index and session endpoints](product-owner/02-auth-session.md)
- [03 — Recovery endpoints](product-owner/03-auth-recovery.md)
- [04 — Account endpoint and lockout](product-owner/04-auth-account.md)
- [05 — Configuration, conventions and pending API decisions](product-owner/05-config-and-conventions.md)
- [06 — Cross-references and inherited Pending decisions](product-owner/06-cross-references.md)

## Notes
- The **security** agent reviews steps 02–05 (timing, enumeration, SSRF, system email
  trust, brute force).
- Keep the issue's decision numbering out of the specs: specs state rules, not issue history.
- Out of scope: device authorization (#29), admin user endpoints and admin recovery (#30),
  organization/SMTP/token/test-email endpoints and the rewrite of `endpoints/admin.md`
  (#31), frontend (#32, including `frontend/screens.md`), the setup flow.
- Verification: specs only — check every relative link resolves
  (e.g. a quick script over `docs/agents/specs/**/*.md`) and that every specific error code
  used is in the `conventions/errors.md` registry.
