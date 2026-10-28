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
      common/ helpers/
      resources/            # one folder per screen area (e.g. organization/, smtpConnections/, apiTokens/)
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
| `ApiClient` | Base JSON client with `getJson`, `postJson`, `patchJson` and `deleteJson`. Sends `credentials: 'same-origin'` (so the `access_token` cookie goes along) and an `Accept: application/json` header; a JSON body and its `Content-Type` header are sent only when the method takes a body, so `getJson` sends neither (also on its `401` retry). Parses the error body (`error.code`, `error.message`, `error.details` and, when present, `error.smtp`, see [api/conventions/errors.md](../api/conventions/errors.md)) into an `ApiError`. Parses `204`, empty and non-JSON bodies as `{}`. On a `401`, refreshes through `POST /auth/refresh.json` with the stored refresh token and retries once; otherwise ends the session (see [screens.md](screens.md#on-401-and-403)). The refresh is single flight within a tab and, when `navigator.locks` is available, across tabs (rule in [screens.md](screens.md#refresh-single-flight)). |
| `AccountsClient` | Auth, account, recovery and device-authorization calls (`register`, `login`, `refresh`, `logout`, `status`, `recover`, `resetPassword`, `updateAccount`, and the five authorization-request methods). Persists the refresh token through `AuthSession` when a call returns one; `logout` always clears it. |
| `AdminClient` | Admin user calls (`searchUsers`, `editUser`, `approveUser`, `denyUser`, `generateRecoveryLink`, `sendRecoveryEmail`). A `403` means a non-admin session. |
| `OrganizationClient` | The caller's own organization (#42): `get` (`GET /organization.json`) and `update({ name })` (`PATCH /organization.json`, only `name` sent). Both answer `{ organization }`. |
| `SmtpConnectionsClient` | The organization's SMTP connections (#43): `list`, `create(attributes)`, `get(id)`, `update(id, attributes)`, `remove(id)`, `activate(id)` (no request body) and `test(id, { to })` on `/smtp-connections*.json`. `create` and `update` send only the accepted fields that are defined (`name`, `host`, `port`, `encryption`, `username`, `password`, `fromAddress`); `active` and unknown keys are never sent. `test` sends `{ to }` only when `to` is a non-empty string, otherwise `{}`. |
| `ApiTokensClient` | The organization's API tokens (#44): `list` (`GET /api-tokens.json`), `create({ name, scopes })` (`POST /api-tokens.json`, only `name` and `scopes` sent, undefined ones dropped) and `revoke(id)` (`DELETE /api-tokens/:id.json`, no request body). The client never keeps a response, in particular the plain `token` returned by `create`. |
| `AuthSession` | The stored refresh token: `localStorage` key `pidgey_refresh_token` (in-memory fallback in Node specs). |
| `AuthStatus` | Runs `POST /auth/status.json` for the stored token and announces the result (logged in, admin, pending) on `AuthEvents`; clears a stale token. |
| `AuthEvents` | `window` event bus for auth-state changes (`loggedIn`, `isAdmin`, `pending`). |
| `LoginModalEvents` | `window` event bus to open the login modal in a given mode, or close it. |
| `ApiError` | Error carrying `status`, `message`, `code`, `details` and, optionally, `smtp` (the SMTP server reply `{ code, message }` from `error.smtp` on `SMTP_*` failures; `undefined` when absent). |
| `pickDefined` | Drops `undefined` entries, so optional fields are sent only when given. |

Each client has one method per endpoint and composes `ApiClient`.

## Pending decisions

None.
