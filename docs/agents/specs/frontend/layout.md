# Frontend Layout

Purpose: define the folder structure of `frontend/` and the API client layer.

```
frontend/
  index.html
  vite.config.js
  package.json
  yarn.lock
  eslint.config.mjs
  assets/js/
    main.jsx                # mounts <App />
    App.jsx                 # root component, hash router
    client/                 # domain API clients
    components/             # React components (screens and shared UI)
      AppController.js
      common/ helpers/ resources/
    utils/                  # pure helpers
      polling/ routing/ validation/
  specs/
    assets/js/              # Jasmine specs mirroring assets/js/, named *Spec.js
      client/ components/ utils/
    support/                # shared test helpers (jsx-loader.mjs, fakeWindow, fetchSequence, …)
```

The layout follows citadel's conventions (the code was copied from citadel's `frontend/`).

## Client layer

The clients in `assets/js/client/` came from Citadel and speak its contract: the `/auth/*`
and `/admin/users/*` routes are the API contract, not something to replace. Every call they
make, and its status against the API spec, is in [api-compatibility.md](api-compatibility.md).

- Components never call `fetch` directly; they use the clients (usually through a page
  controller).
- Domain clients are plain object modules that compose `ApiClient` and expose one method per
  endpoint.

### Clients as they are

| Module | Role |
| --- | --- |
| `ApiClient` | Base JSON client with `postJson`, `patchJson` and `deleteJson`. Sends `credentials: 'same-origin'` (so the `access_token` cookie goes along) and JSON `Content-Type`/`Accept` headers. Parses the error body (`error.code`, `error.message`, `error.details`, see [api/conventions/errors.md](../api/conventions/errors.md)) into an `ApiError`. Parses `204`, empty and non-JSON bodies as `{}`. On a `401`, refreshes through `POST /auth/refresh.json` with the stored refresh token and retries once; otherwise ends the session (see [screens.md](screens.md#on-401-and-403)). |
| `AccountsClient` | Auth, account, recovery and device-authorization calls (`register`, `login`, `refresh`, `logout`, `status`, `recover`, `resetPassword`, `updateAccount`, and the five authorization-request methods). Persists the refresh token through `AuthSession` when a call returns one; `logout` always clears it. |
| `AdminClient` | Admin user calls (`searchUsers`, `editUser`, `approveUser`, `denyUser`, `generateRecoveryLink`, `sendRecoveryEmail`). A `403` means a non-admin session. |
| `AuthSession` | The stored refresh token: `localStorage` key `pidgey_refresh_token` (in-memory fallback in Node specs). |
| `AuthStatus` | Runs `POST /auth/status.json` for the stored token and announces the result (logged in, admin, pending) on `AuthEvents`; clears a stale token. |
| `AuthEvents` | `window` event bus for auth-state changes (`loggedIn`, `isAdmin`, `pending`). |
| `LoginModalEvents` | `window` event bus to open the login modal in a given mode, or close it. |
| `ApiError` | Error carrying `status`, `message`, `code` and `details`. |
| `pickDefined` | Drops `undefined` entries, so optional fields are sent only when given. |

### Target additions

Come with the follow-ups; none exists yet (see
[api-compatibility.md](api-compatibility.md#pidgey-admin)).

- `ApiClient.getJson(path)`: same behaviour as the other methods (credentials, error parsing,
  `401` handling), with no request body (#42).
- `OrganizationClient` (#42): `GET` and `PATCH /organization.json`.
- `SmtpConnectionsClient` (#43): list, create, read, update, delete, activate and test email
  on `/smtp-connections*.json`.
- `ApiTokensClient` (#44): list, create and revoke on `/api-tokens*.json`.
- `ApiClient`'s refresh becomes single flight within a tab and across tabs (#41, rule in
  [screens.md](screens.md#refresh-single-flight)).

Each new client has one method per endpoint and composes `ApiClient`, the same as the
existing clients.

## Pending decisions

None.
