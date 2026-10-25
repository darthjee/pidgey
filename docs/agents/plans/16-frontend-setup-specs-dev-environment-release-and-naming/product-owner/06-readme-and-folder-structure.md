# README and folder-structure (architect)

These files are owned by `architect`, not `product-owner`; the architect applies this step after
steps 01–05, keeping them consistent with the specs.

**`README.md`**
- First run: add `make build-fe-base && make build-fe` and `make fe-install`; after `make dev-up`
  the admin UI is at `http://localhost:8030/admin/`. Link the First run section of
  `docs/agents/specs/frontend/development.md` for details instead of duplicating it.
- Tests and linters: `make tests` also runs frontend `yarn lint && yarn coverage`.
- Continuous integration: mention `frontend_checks` (lint + coverage, `darthjee/circleci_node`).

**`docs/agents/folder-structure.md`**
- `frontend/`: drop *(planned)*; built `dist/` is released into `source/public/admin`.
- `dockerfiles/`: add `vite_pidgey-base/`, `vite_pidgey/`.
- `.circleci/`: add `frontend_checks`.
- `source/public/`: built frontend assets go under `admin/` (git-ignored for now).
- `docker_volumes/node_modules/`: drop *(planned)*.

## Files to Change
- `README.md`
- `docs/agents/folder-structure.md`
