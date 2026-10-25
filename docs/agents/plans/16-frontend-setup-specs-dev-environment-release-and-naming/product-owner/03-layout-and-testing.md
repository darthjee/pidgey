# Fix frontend naming: layout.md and testing specs

**`docs/agents/specs/frontend/layout.md`** — replace the tree with the copied code's layout:

```
frontend/
  index.html
  vite.config.js
  package.json
  yarn.lock
  eslint.config.mjs
  assets/js/
    main.jsx
    App.jsx
    client/                 # domain API clients
    components/
      AppController.js
      common/ helpers/ resources/
    utils/
      polling/ routing/ validation/
  specs/
    assets/js/              # Jasmine specs mirroring assets/js/, named *Spec.js
      client/ components/ utils/
    support/                # shared test helpers (jsx-loader.mjs, fakeWindow, fetchSequence, …)
```

Keep the **target** client list (`ApiClient`, `SessionClient`, `OrganizationsClient`,
`SmtpConnectionsClient`, `ApiTokensClient`) and the client-layer rules. Add a **Current state**
note: `client/` still holds citadel's clients (`ApiClient` with JWT refresh via
`/auth/refresh.json`, `AccountsClient`, `AdminClient`, `AuthSession`, `AuthStatus`,
`AuthEvents`, …), to be replaced by the target clients in follow-up issues; new code must not
build on citadel's `/auth/*` and `/users*` routes.

**`docs/agents/specs/frontend/testing.md`** — specs in `frontend/specs/assets/js/` mirroring
`assets/js/`, named `*Spec.js` (glob `specs/**/*[sS]pec.js`), helpers in `specs/support/`;
`eslint.config.mjs`. Scripts table matching `frontend/package.json`: `build` (`vite build`),
`server` (`vite dev` on 8080 in the container, host 8030), `test` (Jasmine under **nyc**),
`coverage` (Jasmine under **c8**, lcov → `coverage/lcov.info`), `lint` / `lint_fix`
(`eslint assets specs` [`--fix`]). How checks run: `make tests` locally (in `frontend_dev`),
`frontend_checks` in CI — link [deployment/pipeline.md](../deployment/pipeline.md).

**`docs/agents/specs/testing/frontend.md`** — summary: specs mirror `assets/js/` under
`specs/assets/js/`, `*Spec.js`; nyc/c8; `eslint.config.mjs`; commands add `yarn lint_fix`.

## Files to Change
- `docs/agents/specs/frontend/layout.md` — tree + Current state note.
- `docs/agents/specs/frontend/testing.md` — naming, scripts, how checks run.
- `docs/agents/specs/testing/frontend.md` — summary consistency.
