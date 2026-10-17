# Frontend Layout

Purpose: define the folder structure of `frontend/` and the API client layer.

```
frontend/
  index.html
  vite.config.js
  package.json
  eslint.config.js
  assets/js/
    main.jsx            # mounts <App />
    App.jsx             # root component, hash router
    client/             # domain API clients
      ApiClient.js
      OrganizationsClient.js
      SmtpConnectionsClient.js
      ApiTokensClient.js
      SessionClient.js
    components/         # React components (screens and shared UI)
    utils/              # pure helpers
  specs/                # Jasmine specs mirroring assets/js/
    client/ components/ utils/
```

## Client layer

- `ApiClient` is the base class: builds URLs, sends JSON with `fetch`
  (`credentials: 'same-origin'` so the session cookie is sent), parses JSON,
  normalizes errors into the API error shape ([api/conventions.md](../api/conventions.md)).
- Domain clients extend/compose `ApiClient` and expose one method per endpoint
  (e.g. `ApiTokensClient#list`, `#create`, `#revoke`).
- Components never call `fetch` directly; they use the clients.

`SessionClient` (login/logout/register/current session) is an addition to the three
domain clients named in the project brief.
