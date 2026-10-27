# Frontend Plan: Frontend: API tokens screen and ApiTokensClient

Main plan: [plan.md](plan.md)

## Overview
Implement the API tokens management UI against the contract in `docs/agents/specs/api/endpoints/pidgey-admin/api-tokens.md`, mirroring the SMTP connections screens from #43 (`frontend/assets/js/components/resources/smtpConnections/`): a thin client, page components, controllers holding React state setters with an injectable client / `confirm`, and helpers rendering JSX.

## Context
- `ApiClient` already offers `getJson`, `postJson`, `deleteJson`; a session-expired `401` resolves the call to `undefined`, and every controller returns early on `undefined` without touching state.
- `DELETE` answers `204`, parsed as `{}`.
- List rows keep the API order (id ascending). Revoked rows (`revokedAt` set) show a **Revoked** badge with the date and have no Revoke button.
- Create: name (trimmed, 1–255) plus scope checkboxes (`send` only, checked by default); at least one scope is required. The success response's plain `token` is shown once with Copy and a warning; it must never be persisted (no `localStorage` / `sessionStorage` / URL / module variable).
- The header item goes in `renderOrganizationDropdown` (`components/common/header/helpers/HeaderHelper.jsx`) after "SMTP connections".

## Steps

- [01 — ApiTokensClient](frontend/01-api-tokens-client.md)
- [02 — API tokens list screen](frontend/02-api-tokens-list.md)
- [03 — Create screen and one-time token panel](frontend/03-api-token-new.md)
- [04 — Routes and header item](frontend/04-routes-and-header.md)
- [05 — Frontend spec docs](frontend/05-frontend-spec-docs.md)

## CI Checks
- `frontend`: `npm run lint` and `npm run coverage` (CI job: `frontend_checks`)

## Notes
- Coverage thresholds apply: every new module needs Jasmine specs under `frontend/specs/assets/js/` mirroring the source path.
- `navigator.clipboard` may be missing (non-secure context) or reject; the panel must show a fallback message ("Copy failed — select the token and copy it manually") instead of throwing.
- Do not add `Cache-Control` handling or any API change; that is a pending API decision.
