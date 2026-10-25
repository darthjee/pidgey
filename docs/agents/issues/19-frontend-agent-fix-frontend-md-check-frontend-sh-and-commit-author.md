# Issue: Frontend agent: fix frontend.md, check_frontend.sh and commit author

## Description

Part of #15. `.claude/agents/frontend.md` and `.claude/scripts/check_frontend.sh` came from citadel and do not match pidgey yet. This issue fixes them, ports citadel's code-convention guidance to the frontend agent, and sets per-agent commit authors. It also brings `.claude/agents/infra.md` up to date. #16 (specs) and #17 (`frontend_dev`) are already merged.

## Problem

- `.claude/agents/frontend.md`:
  - It lists `eslint.config.js`, but the file is `eslint.config.mjs`.
  - It gives the spec naming as `specs/client/ApiClient_spec.js`. The real convention is `specs/assets/js/client/ApiClientSpec.js`.
  - It is missing `yarn lint_fix` and `yarn build`.
  - It has none of the code conventions that `eslint.config.mjs` enforces.
- `.claude/scripts/check_frontend.sh` runs `yarn test`, not `yarn coverage`. It also omits `--no-deps`, so it starts `app` and `mysql` (`frontend_dev` depends on `app`).
- `.claude/configuration/arcanum-repo-config.json` has no `git` block, so agent commits have no per-agent author.
- `.claude/scripts/check_security.sh` runs `yarn npm audit`, which fails on Yarn 1.22.22.
- `.claude/agents/infra.md` no longer matches the repo:
  - It lists `dockerfiles/pidgey/` (does not exist) and is missing `vite_pidgey-base/`, `vite_pidgey/`, `circleci_pidgey-base/` and `mysql/`.
  - It lists only some of the Makefile targets.
  - It presents `build_images`/`upload_fe_files`/`release` as if they already exist.

## Expected Behavior

- The frontend agent describes pidgey's real layout, commands and conventions.
- `.claude/scripts/check_frontend.sh` runs coverage and lint green inside `frontend_dev`, without starting the backend services.
- Commits made by an agent carry that agent's author email.
- `infra.md` describes what exists and marks future CircleCI jobs as future work.

## Solution

### `.claude/agents/frontend.md`

Fix:
- Scope: `eslint.config.mjs` (not `.js`). Specs mirror under `specs/assets/js/…`, and shared helpers live in `specs/support/`.
- Commands: `docker compose run --rm --no-deps frontend_dev yarn test|coverage|lint|lint_fix|build`. Never run yarn/npm on the host.
- Spec naming: `*Spec.js`, e.g. `assets/js/App.jsx` → `specs/assets/js/AppSpec.js` and `specs/assets/js/client/ApiClientSpec.js`.
- Specs index: `docs/agents/specs/frontend.md` (index) and its `frontend/*.md` files, plus `docs/agents/specs/api.md`.

Port from citadel's `.claude/agents/frontend.md`:
- Code conventions: 2 spaces, single quotes, semicolons, `prefer-const`/no `var`, `eqeqeq`, complexity 10, 300 lines per file, nesting depth 4.
- JSDoc (`@description`, `@param`, `@returns` with descriptions) is required on public code but not in specs.
- Jasmine: no `fdescribe`/`fit`; avoid `xdescribe`/`xit`.
- The section "When to extract JSX into a component vs. a helper method", plus the helper module shape:
  - plain exported objects, writable so specs can `spyOn`;
  - private module-level `renderX` functions;
  - `Map` lookups.
  - Drop citadel's `#227–#230` migration note.
- Development cycle: implement → `yarn test` + `yarn lint_fix` → analyze extraction → repeat.

Keep pidgey's rules:
- Read the specs first, and never implement a Pending decision.
- No direct `fetch`; always go through `client/`.
- Do not touch `source/`, Docker/CI or `docs/agents/specs/`.

Do not port citadel's "Current state" paragraph. Replace it with a short note: the `client/` layer is still citadel's (JWT refresh, `/auth/*`, `/users*`) and will be adapted to pidgey's `/v1` session API in follow-up issues.

### `.claude/scripts/check_frontend.sh`

```bash
docker compose run --rm --no-deps frontend_dev yarn coverage
docker compose run --rm --no-deps frontend_dev yarn lint
```

### `.claude/configuration/arcanum-repo-config.json`

Add a `git` block shaped like citadel's, covering pidgey's agents:

```json
"git": {
  "email": "darthjee+{agent}@gmail.com",
  "omit_model_coauthor": true,
  "merge_body_mode": "coauthors",
  "agents": {
    "architect": "darthjee+architect@gmail.com",
    "backend": "darthjee+backend@gmail.com",
    "frontend": "darthjee+frontend@gmail.com",
    "infra": "darthjee+infra@gmail.com",
    "product-owner": "darthjee+product-owner@gmail.com",
    "security": "darthjee+security@gmail.com",
    "email": "darthjee+email@gmail.com"
  }
}
```

### `.claude/scripts/check_security.sh`

`yarn npm audit` is Yarn 2+ syntax. The image ships Yarn 1.22.22, so that line fails. Replace it with:

```bash
docker compose run --rm --no-deps frontend_dev yarn audit
```

### `.claude/agents/infra.md`

Only make it accurate:
- dockerfiles: `dev_pidgey-base/`, `dev_pidgey/`, `vite_pidgey-base/`, `vite_pidgey/`, `circleci_pidgey-base/`, `mysql/`.
- Makefile targets as they exist, including `build-fe-base`, `push-fe-base`, `build-fe`, `fe-install` and `build-fe-release`.
- `.circleci/`: `backend_checks`, `backend_tests` and `frontend_checks` exist. `build_images`, `upload_fe_files` and `release` stay future work.

### Testing

`.claude/scripts/check_frontend.sh` runs coverage + lint green; the frontend line of `.claude/scripts/check_security.sh` runs (an audit finding is not a failure of this issue).
