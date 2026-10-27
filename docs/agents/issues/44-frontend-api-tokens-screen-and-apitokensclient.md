# Issue: Frontend: API tokens screen and ApiTokensClient

## Description

The admin UI has no way to manage an organization's API tokens. The API contract is already defined in `docs/agents/specs/api/endpoints/pidgey-admin/api-tokens.md` (`GET /api-tokens.json`, `POST /api-tokens.json`, `DELETE /api-tokens/{id}.json`). This issue adds the client and the screens, following the patterns already used by the organization screen (#42) and the SMTP connections screens (#43).

Found while checking frontend/API compatibility in #32 (epic #24). The API specs under `docs/agents/specs/api/**` are the contract; this issue changes frontend code and frontend specs only.

## Problem

- There is no `ApiTokensClient`, so the three API token endpoints are not called (marked **M6** in `docs/agents/specs/frontend/api-compatibility.md`).
- Users cannot list, create or revoke API tokens, so they cannot get a token for `POST /v1/messages`.
- `docs/agents/specs/frontend/screens.md` lists `#/api-tokens` under "Pidgey screens to come".

## Expected Behavior

### `ApiTokensClient`

| Method | Call | Result |
| --- | --- | --- |
| `list()` | `GET /api-tokens.json` (through `ApiClient.getJson`) | the `apiTokens` array |
| `create({ name, scopes })` | `POST /api-tokens.json` with `{ name, scopes }` | the `apiToken` item, including the plain `token` |
| `revoke(id)` | `DELETE /api-tokens/{id}.json`, no body | resolves on `204` |

Errors are surfaced as `ApiError`, as in the other clients.

### Routes and header

- New routes `#/api-tokens` (list) and `#/api-tokens/new` (create).
- The header **Organization** dropdown gets an **API tokens** item → `#/api-tokens`, after **SMTP connections**. Like the other items, it is shown to every logged-in, approved user (no admin check).

### List screen (`#/api-tokens`)

- Loads the tokens with a loading state, an error message when the load fails, and an empty-state message when there are none. A `401` goes through the usual refresh / login-modal flow.
- A **New token** button links to `#/api-tokens/new`.
- Each row shows `name`, `tokenPrefix`, `scopes`, `createdAt` and `lastUsedAt` ("Never" when `null`).
- Rows keep the API order (id ascending). A revoked token (`revokedAt` set) shows a **Revoked** badge with its `revokedAt` date and has no Revoke button.
- An active token has a **Revoke** button. It asks for confirmation through the injectable `confirm` (`window.confirm`), as `AdminUsersController` and the SMTP connections list do. On success the list is reloaded. A `404` reloads the list and shows a message. Other errors are shown as a row/page error.

### Create screen (`#/api-tokens/new`)

- Fields: **Name** and the **Scopes** checkboxes. Only `send` exists today, and it is **checked by default**.
- Client-side validation, with no call made when it fails: the name after trimming must be 1–255 characters, and at least one scope must be checked.
- A `400 VALIDATION_FAILED` maps its details to the name / scopes fields. Any other error is shown as a submit error.
- On success the form is replaced by a **one-time token panel**:
  - it shows the plain `token`, a **Copy** button (`navigator.clipboard.writeText`, with a fallback message if copying fails), and a warning that the token cannot be shown again;
  - a **Done** button goes back to `#/api-tokens`.
- The plain token is **never stored**: it is not written to `localStorage`, `sessionStorage`, the URL or any module-level variable. It lives only in the create screen's component state and is gone once the user leaves the page, through Done or any other navigation.

## Solution

### Changes

- `frontend/assets/js/client/ApiTokensClient.js`: the new client.
- `frontend/assets/js/components/resources/apiTokens/pages/`:
  - `ApiTokens.jsx` (list) and `ApiTokenNew.jsx` (create);
  - controllers and helpers in `controllers/` and `helpers/`, following the `smtpConnections` layout;
  - a token row component and the one-time token panel.
- Validators added to `frontend/assets/js/utils/validation/` as needed.
- `HashRouteResolver.js`: `['/api-tokens/new', 'api-token-new']` and `['/api-tokens', 'api-tokens']`.
- `AppHelper.jsx`:
  - PAGES entries;
  - the **API tokens** dropdown item after **SMTP connections**.
- Frontend specs:
  - `api-compatibility.md`: the three rows move from **M6** to ✅;
  - `screens.md`: move `#/api-tokens` and `#/api-tokens/new` from "Pidgey screens to come" into the route table, document the API tokens screens and the dropdown order;
  - `layout.md`: list the new client and folder.

### Testing

Jasmine specs for:

- `ApiTokensClient`: paths, methods and bodies, and that the result is unwrapped.
- The list controller and helper:
  - load, empty and error states;
  - the revoked badge and that revoked rows have no Revoke action;
  - revoke with confirmation accepted and declined, and the reload;
  - `404` and other errors.
- The create controller and helper:
  - `send` checked by default;
  - name and scope validation;
  - `VALIDATION_FAILED` field mapping;
  - success shows the token panel;
  - Copy calls the clipboard, with the failure fallback.
- That the plain token is not persisted: no write to `localStorage` / `sessionStorage` during or after create, and the token is not present after leaving the create screen.
- Routes and the header dropdown item.

CI: `npm run lint` and `npm run coverage` (job `frontend_checks`).

### Out of scope

- API changes, including the pending `Cache-Control: no-store` on the create response.
- Token expiration, more scopes, renaming tokens, un-revoke.
- The Vite dev proxy for `*.json` paths (#46).

## Benefits

- Organizations can issue and revoke tokens for the sending API from the admin UI, which closes the last **M6** gap in the frontend/API compatibility table.
- The plain token is shown once and never kept in the browser, which matches the API's "returned once, never stored" guarantee.
