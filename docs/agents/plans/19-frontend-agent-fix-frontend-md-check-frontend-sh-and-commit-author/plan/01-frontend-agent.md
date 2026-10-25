# Rewrite the frontend agent definition
Rewrite `.claude/agents/frontend.md` so it matches pidgey and carries citadel's conventions (source: `../citadel/.claude/agents/frontend.md`). Keep the frontmatter (`name`, `description`, `tools`) unchanged.

- **Scope**:
  - `assets/js/` (`main.jsx`, `App.jsx`, `client/`, `components/`, `utils/`);
  - `specs/`, which mirrors `assets/js/` under `specs/assets/js/…`, plus the shared helpers in `specs/support/`;
  - `package.json`, `yarn.lock`, `vite.config.js`, **`eslint.config.mjs`**, `index.html`.
  - Keep the "Do NOT touch `source/`, Docker/CI, `docs/agents/specs/`, anything outside `frontend/`" rule.
- **Stack**: keep the current list.
- **Current state** (replaces citadel's paragraph): the `client/` layer, together with the auth UI built on it, is still citadel's (JWT refresh, `/auth/*`, `/users*`). It will be adapted to pidgey's `/v1` session API (see `docs/agents/specs/api.md`) in follow-up issues. Don't build new screens against the citadel endpoints.
- **Commands**: never run yarn/npm on the host. Always use `docker compose run --rm --no-deps frontend_dev yarn test|coverage|lint|lint_fix|build`, plus `... frontend_dev /bin/bash` for a shell. `make fe-install` installs the dependencies.
- **Code conventions**, as enforced by `frontend/eslint.config.mjs`:
  - 2 spaces, single quotes (except to avoid escaping), semicolons always;
  - `prefer-const`, no `var`, `eqeqeq`;
  - complexity 10, 300 lines per file, nesting depth 4.
- **JSDoc**: required on public functions, classes and methods, with `@description`, `@param` (with description) and `@returns` (with description). Include the example. Not required in `specs/`.
- **Tests (Jasmine)**:
  - files are named `*Spec.js` and mirror the source path, e.g. `assets/js/App.jsx` → `specs/assets/js/AppSpec.js` and `assets/js/client/ApiClient.js` → `specs/assets/js/client/ApiClientSpec.js`;
  - no `fdescribe`/`fit` (error); avoid `xdescribe`/`xit` (warning).
- **When to extract JSX into a component vs. a helper method**: port citadel's section verbatim, including the helper module shape, the `Map` lookups and the quick decision guide. Drop the sentence about older helpers being migrated under `#227–#230`; keep "follow this shape for new code" only if it still reads naturally.
- **Development cycle**: implement → `yarn test` + `yarn lint_fix` (through `frontend_dev`) → analyze extraction → repeat. Never stop after the check step, and never finish with failing tests or lint errors.
- **Conventions to keep** from the current file:
  - read `docs/agents/specs/frontend.md` (index) and the relevant `docs/agents/specs/frontend/*.md` files, plus `docs/agents/specs/api.md`, before coding;
  - never implement a **Pending decision**;
  - components never call `fetch` directly; always go through `client/` (`ApiClient` uses `credentials: 'same-origin'` and normalizes errors);
  - class/component files in PascalCase, `utils/` helpers in camelCase;
  - follow `docs/agents/contributing.md`.

## Files to Change
- `.claude/agents/frontend.md` — rewrite as described.
