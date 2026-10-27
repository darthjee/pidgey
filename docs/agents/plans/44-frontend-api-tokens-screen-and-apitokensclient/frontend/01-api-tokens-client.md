# ApiTokensClient
Add `frontend/assets/js/client/ApiTokensClient.js`, an object module in the style of `SmtpConnectionsClient`, with JSDoc:

- `list()` → `ApiClient.getJson('/api-tokens.json')` (resolves `{ apiTokens }`).
- `create({ name, scopes })` → `ApiClient.postJson('/api-tokens.json', pickDefined({ name, scopes }))`; unknown keys are never sent (resolves `{ apiToken }` including `token`).
- `revoke(id)` → `ApiClient.deleteJson('/api-tokens/${id}.json')`, no body.

Each resolves to `undefined` when the session ended (from `ApiClient`). The client never keeps the response.

## Files to Change
- `frontend/assets/js/client/ApiTokensClient.js` — new client.
- `frontend/specs/assets/js/client/ApiTokensClientSpec.js` — paths, methods, bodies (unknown keys dropped), pass-through of results and `ApiError`s, `undefined` pass-through.
