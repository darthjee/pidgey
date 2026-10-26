# Cross-tab Web Locks guard
Run the shared refresh under a Web Locks API lock so only one tab rotates the token at a time, and re-read the stored token after taking the lock.

In `frontend/assets/js/client/ApiClient.js`:

- Add `const REFRESH_LOCK_NAME = 'pidgey-refresh';`.
- Add `withRefreshLock(callback)`. When `typeof navigator !== 'undefined' && navigator.locks`, it returns `navigator.locks.request(REFRESH_LOCK_NAME, callback)`. Otherwise it returns `callback()`, so only the in-tab single flight applies.
- Wrap `refreshSession(staleToken)`'s body in `withRefreshLock(...)`. Inside the lock, read `const currentToken = AuthSession.get()`:
  - `currentToken` is `null` (another tab's refresh failed or it logged out) → `sessionExpired()`, return `false`;
  - `currentToken !== staleToken` (another tab already rotated it, so the access cookie is fresh) → skip the refresh call and return `true` (retry);
  - otherwise → refresh with `currentToken` as in step 01.
- The in-tab single flight from step 01 stays outside the lock, so a tab holds at most one lock request at a time.
- Update the JSDoc.

Specs (`ApiClientSpec.js`). Install a fake `globalThis.navigator = { locks: { request: jasmine.createSpy().and.callFake((name, cb) => cb()) } }` (or `defineProperty`, if `navigator` is a getter on the Node version) and restore the original in `afterEach`:
- the refresh runs through `navigator.locks.request` with `'pidgey-refresh'`;
- when `AuthSession.get` returns a different token inside the lock than before it, no `/auth/refresh.json` call is made and the original request is retried and resolves;
- when `AuthSession.get` returns `null` inside the lock, no refresh call is made and the session expires once;
- with `navigator.locks` absent, the in-tab flow from step 01 still works (the existing specs, run with `navigator.locks` explicitly removed).

## Files to Change
- `frontend/assets/js/client/ApiClient.js`: `REFRESH_LOCK_NAME`, `withRefreshLock`, re-read of the stored token inside the lock; JSDoc.
- `frontend/specs/assets/js/client/ApiClientSpec.js`: lock-path and fallback specs.
- `frontend/specs/support/fakeWindow.js` (or a new `frontend/specs/support/fakeNavigator.js`): install/uninstall helpers for the fake `navigator.locks`, if more than one spec needs them.
