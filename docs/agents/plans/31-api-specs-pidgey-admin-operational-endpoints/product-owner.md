# Plan: API specs: Pidgey admin & operational endpoints

Issue: [31-api-specs-pidgey-admin-operational-endpoints.md](../../issues/31-api-specs-pidgey-admin-operational-endpoints.md)

## Overview
Write the Citadel-style contracts for the Pidgey-only endpoints: `api/endpoints/pidgey-admin.md`
(index and shared rules) with `pidgey-admin/organization.md`, `pidgey-admin/smtp-connections.md`
(including activate and the test email) and `pidgey-admin/api-tokens.md`; a new
`api/endpoints/operational.md`; the completed `api/endpoints/sending.md`; the removal of the
temporary `api/endpoints/admin.md`; and every cross-reference. No PHP code, migrations or
frontend changes.

## Context
The issue's "Solution" section records every decision (accepted as a whole); follow it
exactly. Reuse the mechanics and wording of the already merged specs:

- `{id}` parsing, operator log and trimming/"absent or `null`" rules from
  `api/endpoints/admin-users.md` and `admin-users/edit.md` (#30);
- the gate and guard order from `api/authentication/gate.md` and
  `api/conventions/format.md`;
- the error envelope, `SMTP_*` codes and `error.smtp` rules from `api/conventions/errors.md`
  and `email/failures.md`; the send flow from `email/send-flow.md`;
- the `smtp_connections` and `api_tokens` tables from `organizations/data-model/*`, and the
  token format from `api/authentication/api-tokens.md`.

Every new spec file follows `docs/agents/specs.md`: title, `Purpose:` line, content,
`## Pending decisions`.

## Steps

- [01 — Pidgey admin index and organization](product-owner/01-pidgey-admin-index-and-organization.md)
- [02 — SMTP connection endpoints](product-owner/02-smtp-connections.md)
- [03 — API token endpoints](product-owner/03-api-tokens.md)
- [04 — Sending and operational endpoints](product-owner/04-sending-and-operational.md)
- [05 — Cross-references and pending items](product-owner/05-cross-references.md)

## Notes
- The **security** agent reviews steps 02–04: SMTP password write-only handling, header
  injection rules on `host`/`fromAddress`, the SSRF known limitation, the plain token returned
  once, token refusal for organizations without an approved user, and operator log content
  (never passwords, plain tokens or hashes).
- The **email** agent may be consulted on step 02's test email (fixed content, failure
  mapping reused from `email/failures.md`).
- No CI check covers specs beyond the usual markdown/link review; check every relative link
  by hand, especially after removing `admin.md`.
- Do not change frontend specs (`frontend/screens.md`, #32).
- The `authorization_requests` retention pending item (`device-authorization.md`) is not
  decided by #31: keep it pending, only drop the "(#31)" reference.
