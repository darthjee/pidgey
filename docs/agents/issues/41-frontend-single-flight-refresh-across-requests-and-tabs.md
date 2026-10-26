# Issue: Frontend: single-flight refresh across requests and tabs

## Description

Make the admin UI run one refresh-token rotation at a time, within a tab and across tabs, so that parallel `401`s never present the same refresh token twice. Found while checking frontend/API compatibility in #32 (epic #24). The API specs under `docs/agents/specs/api/**` are the contract, and the target rule is already written in `docs/agents/specs/frontend/screens.md#refresh-single-flight`. This issue changes frontend code only.

## Problem

`handleUnauthorized` in `frontend/assets/js/client/ApiClient.js` refreshes on every `401` on its own. When two requests (in one tab, or in two tabs sharing the same `localStorage` token) both get a `401`, each one posts the same stored refresh token to `POST /auth/refresh.json`. The first call rotates the token. The second call then presents a `rotated` token, which the API treats as a replay: it revokes **every session of the user** (`docs/agents/specs/api/authentication/sessions.md#replay-detection`, "Multi-tab race"). The user is logged out everywhere.

`AccountsClient.refresh` (`frontend/assets/js/client/AccountsClient.js`) is a second refresh path, also sent through `ApiClient.postJson`. Live code never calls it, only `AccountsClientSpec.js` does. If it were used, it would bypass any single flight, and a `401` on it would make `handleUnauthorized` refresh the same token again.

## Expected Behavior

- **Within a tab:** one in-flight refresh promise is shared by every request that got a `401`. When the promise settles, each of those requests retries once with the new access cookie. A `401` on that retry ends the session, as today.
- **Across tabs:** the refresh runs under a Web Locks API lock (`navigator.locks.request('pidgey-refresh', ...)`). After taking the lock, the client re-reads the stored refresh token (`AuthSession.get()`):
  - if it differs from the token that was current when the `401` happened, another tab already rotated it, so the client skips the refresh and just retries;
  - if it is now missing (another tab's refresh failed or the user logged out), the session ends;
  - otherwise the client refreshes, stores the new token, and the requests retry.
- **Without `navigator.locks`** (old browsers, Node-based Jasmine specs): only the in-tab single flight applies.
- **A failed refresh** ends the session **once**: one `sessionExpired()` call for the shared refresh, not one per waiting request. Each waiting request then resolves to `undefined`, as an expired session does today.
- `AccountsClient.refresh` and its specs are deleted, so the `ApiClient` single flight is the only refresh path.

## Solution

### Changes

- `frontend/assets/js/client/ApiClient.js`:
  - keep a module-level in-flight refresh promise, cleared when it settles;
  - `handleUnauthorized` captures the current stored token, joins the in-flight promise (or starts one), then retries once on success;
  - the refresh body runs under `navigator.locks.request('pidgey-refresh', ...)` when `navigator.locks` exists, and runs directly otherwise;
  - inside the lock, re-read the stored token and skip, refresh or expire as described above;
  - `sessionExpired()` is called once by the shared refresh on failure;
  - update the JSDoc of `handleUnauthorized` and of the `ApiClient` object to describe the single flight.
- `frontend/assets/js/client/AccountsClient.js`: delete `refresh` and fix the class JSDoc that lists "refresh" among its requests.
- `frontend/specs/assets/js/client/AccountsClientSpec.js`: delete the `.refresh` case.

### Testing (Jasmine)

- Two concurrent requests that both get a `401` cause exactly one `POST /auth/refresh.json`, and both retry and resolve.
- A failed shared refresh calls `sessionExpired` (clears `AuthSession`, opens the login modal) once, and every waiting request resolves to `undefined`.
- With a stubbed `navigator.locks`:
  - the refresh runs inside a `'pidgey-refresh'` lock;
  - when the stored token changed while waiting for the lock, no refresh call is made and the request retries;
  - when the stored token was cleared while waiting, the session ends without a refresh call.
- Without `navigator.locks`, the in-tab single flight still works. Specs must set or remove `navigator.locks` explicitly, and must not depend on whether the Node runtime provides it.
- After the in-flight refresh settles, a later `401` starts a new refresh (the promise is not cached forever).

### Out of scope

- Proactive refresh before the access token expires.
- Syncing login/logout state between tabs through `storage` events.
- Any API change: the strict replay rule stays as specified.
- Spec docs under `docs/agents/specs/frontend/**`: update `api-compatibility.md` (M2 for #41) only if the plan finds it necessary.

## Benefits

- Parallel requests after an access-token expiry no longer log the user out of every session.
- Two open admin tabs can expire and refresh at the same time safely.
- There is one refresh path, which is easier to reason about and test.
