# OrganizationClient
Create `frontend/assets/js/client/OrganizationClient.js`, an object module composing `ApiClient` like `AdminClient`:

- `get()` → `ApiClient.getJson('/organization.json')`, resolving to `{ organization: { id, name, createdAt, updatedAt } }`.
- `update({ name })` → `ApiClient.patchJson('/organization.json', { name })`, resolving to `{ organization }`.

Document both methods with JSDoc: the response shape, and that a `401` resolves to `undefined` through `ApiClient`. Note in the module JSDoc that the organization is always the caller's own, so there is no id in the path.

Specs (`frontend/specs/assets/js/client/OrganizationClientSpec.js`), spying on `ApiClient` like `AdminClientSpec.js`:
- `get` calls `getJson('/organization.json')` and resolves with its result;
- `update` calls `patchJson('/organization.json', { name })` and resolves with its result;
- `update` sends only `name`, even when the argument has extra keys.

## Files to Change
- `frontend/assets/js/client/OrganizationClient.js` — new client.
- `frontend/specs/assets/js/client/OrganizationClientSpec.js` — new specs.
