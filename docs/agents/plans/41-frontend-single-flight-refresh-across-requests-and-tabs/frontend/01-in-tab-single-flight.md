# In-tab single-flight refresh
Share one in-flight refresh between every request in the tab that got a `401`, and end the session only once when that refresh fails.

In `frontend/assets/js/client/ApiClient.js`:

- Add a module-level `let refreshInFlight = null;`.
- Extract a `refreshSession(staleToken)` function that performs the refresh and returns a boolean outcome (`true` means "retry", `false` means "session ended"):
  - no `staleToken` → `sessionExpired()`, return `false`;
  - `POST /auth/refresh.json` with `{ refreshToken: staleToken }`;
  - not ok → `sessionExpired()`, return `false`;
  - ok → `AuthSession.set(data.refreshToken)`, return `true`.
  - A network error (a rejected `fetch`) should also end the session through the same `false` path and not leave the promise hanging, or should rethrow to every waiter. Pick one, document it in JSDoc, and spec it. Ending the session matches the existing "failed refresh" semantics.
- Add `sharedRefresh(staleToken)`: if `refreshInFlight` is set, return it. Otherwise set `refreshInFlight = refreshSession(staleToken).finally(() => { refreshInFlight = null; })` and return it.
- `handleUnauthorized(method, path, body)`:
  - read `const staleToken = AuthSession.get()`;
  - `await sharedRefresh(staleToken)`;
  - on `true`, return `sendJson(method, path, body, true)`;
  - on `false`, return `undefined` without calling `sessionExpired()` again.
- Update the JSDoc of `handleUnauthorized`, the new helpers and the `ApiClient` object to describe the single flight.

Specs in `frontend/specs/assets/js/client/ApiClientSpec.js` (`401 handling`); the existing four cases must keep passing:
- Two concurrent `postJson` calls that both get a `401` cause exactly one `/auth/refresh.json` call. Both retry and resolve with their own bodies.
- A failed shared refresh: `AuthSession.clear` and `LoginModalEvents.open` are each called once, and both requests resolve to `undefined`.
- A `401` after the first refresh has settled starts a new refresh: two refresh calls in total.

## Files to Change
- `frontend/assets/js/client/ApiClient.js`: in-flight promise, `refreshSession` and `sharedRefresh`; `handleUnauthorized` joins the shared refresh; JSDoc.
- `frontend/specs/assets/js/client/ApiClientSpec.js`: concurrency specs.
- `frontend/specs/support/fetchSequence.js`: add a URL-keyed fetch fake helper only if the specs need one (keep the existing helpers unchanged).
