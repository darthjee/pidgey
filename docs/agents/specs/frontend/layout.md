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

Target clients in `assets/js/client/`: `ApiClient`, `SessionClient`, `OrganizationsClient`,
`SmtpConnectionsClient`, `ApiTokensClient`.

- `ApiClient` is the base class: builds URLs, sends JSON with `fetch`
  (`credentials: 'same-origin'` so the session cookie is sent), parses JSON,
  normalizes errors into the API error envelope
  ([api/conventions/errors.md](../api/conventions/errors.md)).
- Domain clients extend/compose `ApiClient` and expose one method per endpoint
  (e.g. `ApiTokensClient#list`, `#create`, `#revoke`).
- Components never call `fetch` directly; they use the clients.

`SessionClient` (login/logout/register/current session) is an addition to the three
domain clients named in the project brief.

## Current state

`client/` still holds citadel's clients (`ApiClient` with JWT refresh via `/auth/refresh.json`,
`AccountsClient`, `AdminClient`, `AuthSession`, `AuthStatus`, `AuthEvents`, …). They are to be
replaced by the target clients above in follow-up issues; new code must not build on citadel's
`/auth/*` and `/users*` routes.
