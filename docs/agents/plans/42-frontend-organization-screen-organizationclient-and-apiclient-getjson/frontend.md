# Plan: Frontend: organization screen, OrganizationClient and ApiClient.getJson

Issue: [42-frontend-organization-screen-organizationclient-and-apiclient-getjson.md](../../issues/42-frontend-organization-screen-organizationclient-and-apiclient-getjson.md)

## Overview
Add the admin UI's Organization screen, where any logged-in, approved user can see and rename their own organization. It needs three pieces of plumbing:
- a `GET` method on `ApiClient` (`getJson`);
- an `OrganizationClient` with one method per endpoint;
- a new **Organization** header dropdown, which #43 (SMTP connections) and #44 (API tokens) will add their items to later.

## Context
- The API contract is `docs/agents/specs/api/endpoints/pidgey-admin/organization.md`.
  - `GET /organization.json` returns `200 { organization }`.
  - `PATCH /organization.json` takes `{ name }` (required, trimmed, 1–255 characters, not unique) and returns `200 { organization }`, reloaded after the update.
  - Validation failures return `400 VALIDATION_FAILED`, with `error.details` as an array of messages, e.g. `"name should not be empty"` (see `docs/agents/specs/api/conventions/errors.md`).
- `ApiClient` (`frontend/assets/js/client/ApiClient.js`) only has `postJson`, `patchJson` and `deleteJson`, all built on `sendJson(method, path, body, isRetry)`. That function already joins the single-flight refresh (#41) and retries once on `401`. `request()` currently always sends `Content-Type: application/json` and `JSON.stringify(body)`.
- Existing page patterns:
  - **Load on mount:** `AuthorizationRequests.jsx` uses an exported `buildLoadEffect(controller)`, a `useMemo` controller and a `useEffect`. Its controller sets `loadError`.
  - **Form:** `MyAccount` / `AdminUserEdit` use `useAccountEditForm` plus `AccountEditFormController`. Those are account-specific (username/email/password), so the Organization screen gets its own small controller instead of reusing them. It still reuses `FormFieldsHelper` (`renderField`, `renderSubmitError`, success alert) for rendering.
- Routing is in `frontend/assets/js/utils/routing/HashRouteResolver.js` (the `ROUTES` table). Pages are registered in `frontend/assets/js/components/helpers/AppHelper.jsx` (the `PAGES` map). The header's links are in `frontend/assets/js/components/common/header/helpers/HeaderHelper.jsx`, where `renderAuthLinks` hides every link while the session is pending.
- Dev-proxy caveat: Vite only proxies `/v1`, so the screen cannot reach the API under `make dev-up` until #46 lands. That is out of scope here; specs don't need it.

## Steps

- [01 — ApiClient.getJson](frontend/01-api-client-get-json.md)
- [02 — OrganizationClient](frontend/02-organization-client.md)
- [03 — Organization screen](frontend/03-organization-screen.md)
- [04 — Route, page registration and header dropdown](frontend/04-route-and-header.md)
- [05 — Frontend spec docs](frontend/05-frontend-spec-docs.md)

## CI Checks
- `frontend`: `npm run lint` and `npm run coverage` (CI job: `frontend_checks`)

## Notes
- Don't touch `docs/agents/specs/api/**`; it is the contract.
- The screen is **not** admin-only: don't add a `redirectIfForbidden` / admin gate. Access follows the session (logged in and approved), and the backend's gate answers `401` otherwise, which `ApiClient` handles.
- Out of scope:
  - the Vite `*.json` proxy (#46);
  - the SMTP connections (#43) and API tokens (#44) screens;
  - showing `createdAt` / `updatedAt`.
- Follow the repo's conventions: JSDoc on every exported function and method; object-module helpers; an injectable client in controller constructors, for testability. Coverage must stay complete, since `c8` runs in CI.
