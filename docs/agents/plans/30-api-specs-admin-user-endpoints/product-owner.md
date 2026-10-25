# Plan: API specs: admin user endpoints

Issue: [30-api-specs-admin-user-endpoints.md](../../issues/30-api-specs-admin-user-endpoints.md)

## Overview
Write `api/endpoints/admin-users.md` (index) with `admin-users/review.md` (search, approve,
deny), `admin-users/edit.md` (edit) and `admin-users/recovery.md` (recovery-link,
send-recovery-email), plus the cross-reference updates. No PHP code, migrations or frontend
changes.

## Context
The frontend already calls the Citadel admin contract
(`frontend/assets/js/client/AdminClient.js`, `components/resources/admin/pages/*`). The admin
check is in `api/authentication/gate.md`, reset tokens and the recovery email in
`api/endpoints/auth/recovery.md`, and `USER_NOT_PENDING` / `CANNOT_DENY_SELF` are already
registered in `api/conventions/errors.md`. The issue's "Solution" section records every
decision; follow it exactly, and reuse the wording and mechanics of #28's specs
(`api/endpoints/auth.md` trimming and password hashing, `auth/account.md` "taken" checks).

Every new spec file follows `docs/agents/specs.md`: title, `Purpose:` line, content,
`## Pending decisions`.

## Steps

- [01 — Admin users index](product-owner/01-admin-users-index.md)
- [02 — Review endpoints](product-owner/02-review.md)
- [03 — Edit endpoint](product-owner/03-edit.md)
- [04 — Recovery endpoints](product-owner/04-recovery.md)
- [05 — Cross-references](product-owner/05-cross-references.md)

## Notes
- The **security** agent reviews steps 01–04: the admin trust level, session revocation on
  admin edits, recovery-token handling, operator log content (never values, tokens or links)
  and the documented known limitations.
- No CI check covers specs beyond the usual markdown/link review; check every relative link
  by hand.
- Do not change frontend specs (#32) or the Pidgey admin endpoints in `admin.md` beyond the
  pending-entry removal (#31).
