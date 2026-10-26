# SmtpConnectionsClient
New object-module client, following `OrganizationClient`. Every method returns the
`ApiClient` result (`undefined` when the session ended).

| Method | Call |
| --- | --- |
| `list()` | `ApiClient.getJson('/smtp-connections.json')` |
| `create(attributes)` | `ApiClient.postJson('/smtp-connections.json', body)` |
| `get(id)` | `ApiClient.getJson('/smtp-connections/{id}.json')` |
| `update(id, attributes)` | `ApiClient.patchJson('/smtp-connections/{id}.json', body)` |
| `remove(id)` | `ApiClient.deleteJson('/smtp-connections/{id}.json')` |
| `activate(id)` | `ApiClient.postJson('/smtp-connections/{id}/activate.json')` — no body |
| `test(id, { to } = {})` | `ApiClient.postJson('/smtp-connections/{id}/test.json', body)` |

- `create` / `update` send only the accepted keys (`name`, `host`, `port`, `encryption`,
  `username`, `password`, `fromAddress`) and only those that are defined (use
  `client/pickDefined.js`); `active` and unknown keys are never sent. Callers decide which
  keys to include (e.g. `password` only when typed, `""` to clear).
- `test` sends `{ to }` only when `to` is a non-empty string, otherwise `{}`.
- Check what `postJson` does with an `undefined` body after #42 (`request` omits body and
  `Content-Type` when the body is `undefined`); `activate` relies on that.
- Specs: path and method for each call, `{id}` interpolation, filtered bodies (no `active`,
  undefined keys dropped, `""` kept), `test` with and without `to`, `activate` without body,
  and passing through an `undefined` result.

## Files to Change
- `frontend/assets/js/client/SmtpConnectionsClient.js` — new client.
- `frontend/specs/assets/js/client/SmtpConnectionsClientSpec.js` — new specs.
