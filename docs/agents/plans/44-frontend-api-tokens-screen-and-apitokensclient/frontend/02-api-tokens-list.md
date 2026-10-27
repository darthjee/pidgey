# API tokens list screen
Add the `#/api-tokens` page under `frontend/assets/js/components/resources/apiTokens/pages/`:

- `ApiTokens.jsx`: holds `tokens`, `loadState` (`{ loading, loadError }`) and `listError` state, creates the controller, calls `load()` on mount, and renders through the helper.
- `controllers/ApiTokensController.js`, constructor `{ setTokens, setLoadState, setListError, client = ApiTokensClient, confirm = defaultConfirm }`:
  - `load()`: `client.list()`; on a result, `setTokens(result.apiTokens)`; always clear loading; on error store `loadError`; on `undefined` stay idle.
  - `handleRevoke(token)`: ask `confirm(REVOKE_CONFIRM_MESSAGE)` (exported constant, e.g. "Revoke this API token? Integrations using it stop working immediately. This cannot be undone."); cancel sends nothing. On success, reload through `load()` so `revokedAt` comes from the API. On `404` (`error.status === 404`), reload and show "This API token no longer exists." as the list error. Other errors → `setListError(error.message)`.
- `helpers/ApiTokensHelper.jsx`: loading spinner/text, load error alert, list error alert, empty state ("No API tokens yet."), a **New token** link to `#/api-tokens/new`, and a table with name, `tokenPrefix` (rendered as `<code>`, followed by "…"), scopes (comma-joined), `createdAt`, `lastUsedAt` ("Never" when `null`) and a status/action cell.
- `tokens/ApiTokenRow.jsx` (or inline in the helper if small): revoked → `Badge` "Revoked" plus the `revokedAt` date and no button; active → a **Revoke** button calling `handleRevoke(token)`.
- Dates: format with `toLocaleString()` from the ISO value; reuse an existing date helper if one exists in `utils/`.

## Files to Change
- `frontend/assets/js/components/resources/apiTokens/pages/ApiTokens.jsx` — new page.
- `frontend/assets/js/components/resources/apiTokens/pages/controllers/ApiTokensController.js` — new controller.
- `frontend/assets/js/components/resources/apiTokens/pages/helpers/ApiTokensHelper.jsx` — new helper.
- `frontend/assets/js/components/resources/apiTokens/pages/tokens/ApiTokenRow.jsx` — row component.
- `frontend/specs/assets/js/components/resources/apiTokens/pages/**` — specs: load / empty / error / idle-on-undefined; revoked badge and no Revoke button; revoke confirmed and cancelled; reload after revoke; `404` and other errors.
