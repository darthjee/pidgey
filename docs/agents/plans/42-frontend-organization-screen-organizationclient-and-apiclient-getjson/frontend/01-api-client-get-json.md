# ApiClient.getJson
Add `getJson(path)` to `ApiClient`. It sends a `GET` with no request body and has the same credentials, error parsing and `401` handling (single-flight refresh, one retry, `undefined` when the session ended) as the other methods.

- Make `request(method, path, body)` send a body only when one is given:
  - when `body === undefined`, omit both `body` and the `Content-Type` header, keeping `Accept: application/json` and `credentials: 'same-origin'`;
  - otherwise send them exactly as today.
- `getJson(path)` returns `sendJson('GET', path)`. The `401` retry goes through `sendJson(method, path, body, true)` with `body` still `undefined`, so the retried `GET` also has no body.
- Update the `ApiClient` object's JSDoc (method list) and `request`'s JSDoc (`body` is now optional).

Specs (`frontend/specs/assets/js/client/ApiClientSpec.js`, new `describe('.getJson')`), using the existing `fetchSequence` / `fakeResponse` helpers:
- sends `GET` with `credentials: 'same-origin'`, `Accept: application/json`, no `body` and no `Content-Type`;
- resolves with the parsed body on success;
- throws an `ApiError` with status, message, code and details on a non-`401` failure;
- on a `401`, refreshes and retries once, and the retried request is a `GET` with no body;
- the existing `postJson` / `patchJson` / `deleteJson` specs still pass unchanged (they still send a body and `Content-Type`).

## Files to Change
- `frontend/assets/js/client/ApiClient.js` — `request` sends a body only when one is given; add `getJson`.
- `frontend/specs/assets/js/client/ApiClientSpec.js` — `.getJson` specs, including the `401` retry.
