# Plan: API specs: device-authorization endpoints

Issue: [29-api-specs-device-authorization-endpoints.md](../../issues/29-api-specs-device-authorization-endpoints.md)

## Overview
Write `api/endpoints/auth/device-authorization.md` (index) with
`device-authorization/requester.md` and `device-authorization/approver.md`, a new
`api/conventions/client-ip.md`, the `AUTHORIZATION_REQUEST_*` settings, and the
cross-reference updates. No PHP code, migrations or frontend changes.

## Context
The frontend already calls the Citadel device-authorization contract
(`frontend/assets/js/client/AccountsClient.js`, `utils/polling/AuthorizationRequestPoller.js`).
The `authorization_requests` table (`organizations/data-model/authorization-requests.md`) and
`TRUSTED_PROXY_HOPS` already exist. The issue's "Expected Behavior" section records every
decision (1–9); follow it exactly, and reuse the wording and mechanics of #28's specs
(`api/endpoints/auth.md`, `auth/session.md`, `auth/account.md` lockout).

Every new spec file follows `docs/agents/specs.md`: title, `Purpose:` line, content,
`## Pending decisions`.

## Steps

- [01 — Device-authorization index](product-owner/01-device-authorization-index.md)
- [02 — Requester endpoints](product-owner/02-requester.md)
- [03 — Approver endpoints](product-owner/03-approver.md)
- [04 — Client IP and configuration](product-owner/04-client-ip-and-config.md)
- [05 — Cross-references](product-owner/05-cross-references.md)

## Notes
- The **security** agent reviews steps 02–04 (enumeration safety, claim atomicity, lockout,
  client-IP spoofing, documented known limitations).
- No CI check covers specs beyond the usual markdown/link review; check every relative link
  by hand.
- Do not change `frontend/screens.md` or other frontend specs (#32).
