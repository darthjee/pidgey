# Issue: Frontend: organization screen, OrganizationClient and ApiClient.getJson

## Description

Add the admin UI's Organization screen. It lets any logged-in, approved user see and rename their own organization through `GET` and `PATCH /organization.json` ([organization.md](docs/agents/specs/api/endpoints/pidgey-admin/organization.md)). This needs a `GET` method on `ApiClient` and a new `OrganizationClient`.

Found while checking frontend/API compatibility in #32 (epic #24); tracked as **M6** in `docs/agents/specs/frontend/api-compatibility.md`. The API specs under `docs/agents/specs/api/**` are the contract; this issue changes frontend code (and the frontend spec docs' status) only.

## Problem

- The frontend has no client or screen for the organization.
- `ApiClient` only has `postJson`, `patchJson` and `deleteJson`. There is no `GET` method, which the Pidgey admin endpoints (organization now, SMTP connections #43 and API tokens #44 later) need.

## Expected Behavior

### `ApiClient.getJson(path)`

- Sends a `GET` with `credentials: 'same-origin'` and `Accept: application/json`, and **no request body**. No `Content-Type` header is needed when there is no body.
- Error parsing (`ApiError`) and `401` handling are the same as the other methods: it joins the single-flight refresh (#41), retries once, and resolves to `undefined` when the session ended.

### `OrganizationClient`

New `frontend/assets/js/client/OrganizationClient.js`. It composes `ApiClient` with one method per endpoint, like the existing clients:

- `get()` → `GET /organization.json` → `{ organization }`
- `update({ name })` → `PATCH /organization.json` with `{ name }` → `{ organization }`

### Organization screen

- New route `#/organization` (page key `organization`). It follows the existing page / controller / helper pattern (e.g. `MyAccount`).
- On load, it calls `OrganizationClient.get()` and fills the **Organization name** field.
  - It shows a loading state while the call runs.
  - If the load fails, it shows an error message.
  - A `401` is handled by `ApiClient`'s refresh / login-modal flow; an `undefined` result just leaves the screen idle.
- On save:
  - Client-side validation: the name, after trimming, must be 1–255 characters. If it fails, show an inline error and make no API call.
  - Otherwise call `update({ name })`. On success, show a confirmation and put the returned `organization.name` (as stored, trimmed) back into the field.
  - On `400 VALIDATION_FAILED`, show the error details on the name field. Any other error appears as a submit error.
- Access: every logged-in, approved user, not only admins. Every user of an organization has the same access ([screens.md](docs/agents/specs/frontend/screens.md#pidgey-screens-to-come)).

### Header

- New **Organization** dropdown, shown for any logged-in, approved user. It is hidden when logged out and while the session is pending, like the other auth links. For now it has one item, **Settings** → `#/organization`.
- #43 (SMTP connections) and #44 (API tokens) will add their items to this same dropdown.

## Solution

### Changes

- `frontend/assets/js/client/ApiClient.js`: add `getJson(path)`. `request` sends a body and `Content-Type` only when a body is given, so the `401` retry of a `GET` also sends no body.
- `frontend/assets/js/client/OrganizationClient.js`: new file.
- `frontend/assets/js/components/resources/organization/pages/`:
  - `Organization.jsx`
  - `controllers/OrganizationController.js`: load, validate and submit.
  - `helpers/OrganizationHelper.jsx`: rendering.
- `frontend/assets/js/utils/routing/HashRouteResolver.js`: add `['/organization', 'organization']`.
- `frontend/assets/js/components/helpers/AppHelper.jsx`: register the `organization` page.
- `frontend/assets/js/components/common/header/helpers/HeaderHelper.jsx`: add the Organization dropdown.
- Frontend spec docs: mark the organization rows and target additions as implemented in:
  - `docs/agents/specs/frontend/api-compatibility.md`
  - `docs/agents/specs/frontend/layout.md`
  - `docs/agents/specs/frontend/screens.md`

### Testing (Jasmine)

- `ApiClient.getJson`:
  - sends `GET` with no body and with same-origin credentials;
  - parses errors into `ApiError`;
  - on a `401`, refreshes and retries once (still with no body).
- `OrganizationClient`: each method hits the right method, path and body.
- `OrganizationController`:
  - load success and load failure;
  - validation (empty, whitespace-only, 256 characters);
  - save success reflecting the returned name;
  - `400 VALIDATION_FAILED` details shown on the field;
  - other errors;
  - an `undefined` (session ended) result.
- `OrganizationHelper`, route resolution and the header dropdown: visible for a logged-in, approved user; hidden when logged out or pending.
- `npm run lint` and `npm run coverage` pass.

### Out of scope

- The Vite `*.json` dev proxy. Until #46 lands, `/organization.json` does not reach the API in `make dev-up`. #46 is a dependency only for trying the screen in dev; specs do not need it.
- The SMTP connections (#43) and API tokens (#44) screens, beyond leaving room for them in the dropdown.
- Any change to the API or the API specs.
- Showing `createdAt` / `updatedAt`.

## Benefits

- Users can see and rename their organization from the admin UI. This closes compatibility gap M6 for the organization endpoints.
- `ApiClient.getJson` and the Organization dropdown are the shared base #43 and #44 build on.
