# Frontend Plan: Frontend: single-flight refresh across requests and tabs

Main plan: [plan.md](plan.md)

## Overview
`handleUnauthorized` in `frontend/assets/js/client/ApiClient.js` refreshes on every `401` on its own, so parallel `401`s re-present a rotated refresh token and the API revokes every session of the user (replay detection). This plan:

- adds an in-tab single flight;
- runs the refresh under a cross-tab `navigator.locks` lock that re-reads the stored token;
- ends the session once per failed refresh;
- deletes the unused `AccountsClient.refresh`.

## Context
- Contract: `docs/agents/specs/api/authentication/sessions.md#replay-detection` (strict replay rule, no grace window) and `docs/agents/specs/frontend/screens.md#refresh-single-flight` (target frontend rules).
- The refresh token lives in `localStorage` under `pidgey_refresh_token` (`AuthSession`), so every tab sees the same value. The access token is an HttpOnly cookie, also shared by every tab.
- Today's 401 flow: `sendJson` → `handleUnauthorized` → `request('POST', '/auth/refresh.json', { refreshToken })` → `AuthSession.set(new)` → `sendJson(..., isRetry = true)`. A `401` on the retry calls `sessionExpired()`.
- `AccountsClient.refresh` has no live callers, only `AccountsClientSpec.js` (decision recorded in the issue: delete it).
- Specs run Jasmine on Node. `fetch` is faked with `fetchSequence`/`stubRefreshFlow` (`frontend/specs/support/fetchSequence.js`). Node may or may not expose `navigator.locks`, depending on its version.

## Steps

- [01 — In-tab single-flight refresh](frontend/01-in-tab-single-flight.md)
- [02 — Cross-tab Web Locks guard](frontend/02-cross-tab-web-locks.md)
- [03 — Delete AccountsClient.refresh](frontend/03-delete-accounts-client-refresh.md)

## CI Checks
- `frontend`: `npm run lint` and `npm run coverage` (CI job: `frontend_checks`)

## Notes
- Do not change `docs/agents/specs/api/**`: the API contract stays as it is.
- `docs/agents/specs/frontend/api-compatibility.md` marks #41 as M2. Updating that row to "done" is optional and must not touch other rows.
- Concurrency specs must not depend on the order in which interleaved `fetch` calls happen. Prefer a URL-keyed fake (for example, `/auth/refresh.json` → a refresh response, other paths → `401` first, then `200`) over a single ordered `fetchSequence`.
- Every spec must set or delete `navigator.locks` explicitly and restore it in `afterEach`, so results do not depend on the Node version.
- Proactive refresh, `storage`-event tab sync and any API change are out of scope.
