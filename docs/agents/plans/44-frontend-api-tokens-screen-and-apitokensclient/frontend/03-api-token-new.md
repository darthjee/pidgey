# Create screen and one-time token panel
Add the `#/api-tokens/new` page:

- `ApiTokenNew.jsx`: state `name`, `scopes` (initially `['send']`), `fieldErrors`, `submitError`, `submitting`, and `createdToken` (the plain token string, `null` until success). The plain token lives **only** in this component's state, so it is dropped when the page unmounts (Done or any navigation).
- `controllers/ApiTokenNewController.js`, constructor with the state setters and `client = ApiTokensClient`:
  - `handleSubmit({ name, scopes })`: validate first; on failure set `fieldErrors` and make no call. Send `{ name: name.trim(), scopes }`. On a result, `setCreatedToken(result.apiToken.token)`; on `undefined` return. A `400 VALIDATION_FAILED` maps `error.details` to `name` / `scopes` (messages starting with `name` / `scopes`, like `SmtpConnectionFormRules.mapValidationDetails`); unmatched details and any other error → `submitError`.
  - `handleCopy(token)`: `await navigator.clipboard.writeText(token)` through an injectable `clipboard` (default `navigator.clipboard`), setting a "Copied" state; when the clipboard is missing or rejects, set the fallback message.
  - `toggleScope(scope)`.
- Validation: add `validateApiTokenName(name)` (trimmed, required, max 255 via existing `validateMaxLength`) and `validateScopes(scopes)` (at least one) to `frontend/assets/js/utils/validation/fieldValidators.js`, or to a small `ApiTokenFormRules.js` next to the controller if that matches #43 better.
- `helpers/ApiTokenNewHelper.jsx`: the form (Name input, one checkbox per known scope from an exported `API_TOKEN_SCOPES = ['send']`, Create and Cancel → `#/api-tokens`), or, once `createdToken` is set, `tokens/ApiTokenCreatedPanel.jsx`: a warning alert ("Copy this token now. It cannot be shown again."), the token in a read-only `<code>`/input, **Copy** and **Done** (`#/api-tokens`).
- Never write the token to `localStorage`, `sessionStorage`, the URL or a module-level variable; never log it.

## Files to Change
- `frontend/assets/js/components/resources/apiTokens/pages/ApiTokenNew.jsx` — new page.
- `frontend/assets/js/components/resources/apiTokens/pages/controllers/ApiTokenNewController.js` — new controller.
- `frontend/assets/js/components/resources/apiTokens/pages/helpers/ApiTokenNewHelper.jsx` — new helper.
- `frontend/assets/js/components/resources/apiTokens/pages/tokens/ApiTokenCreatedPanel.jsx` — one-time token panel.
- `frontend/assets/js/utils/validation/fieldValidators.js` — name / scopes validators (if placed here).
- `frontend/specs/assets/js/components/resources/apiTokens/pages/**` and `frontend/specs/assets/js/utils/validation/fieldValidatorsSpec.js` — specs: `send` checked by default; name / scope validation with no call; trimmed name sent; `VALIDATION_FAILED` mapping; other errors; success shows the panel; Copy success and failure fallback; spies on `localStorage.setItem` / `sessionStorage.setItem` never called with the token; after unmount and remount the token is gone.
