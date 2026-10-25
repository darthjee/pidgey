# Update pipeline, architecture and overview specs

**`deployment/pipeline.md`**
- "Every job runs in `circleci_pidgey-base`" → backend jobs do; `frontend_checks` runs in
  `darthjee/circleci_node:0.2.1`.
- `frontend_checks`: no longer future work — moves `frontend/` to the working dir,
  `yarn install`, `npm run lint`, `npm run coverage`.
- Workflow: `frontend_checks` runs in parallel with the backend jobs on every branch and tag.
- `upload_fe_files` (still future work): uploads `dist/` to the web root's `admin/`.
- Codacy: frontend coverage upload (partial + final) stays a Pending decision; `.codacy.yml`
  also excludes `frontend/coverage/**`, `frontend/node_modules/**`, `**/yarn.lock`.
- Pending "upload mechanism for `dist/`" → link the production delivery decision in
  [frontend/build.md](../frontend/build.md).

**`architecture/repository-layout.md`**
- `dockerfiles/` row: add `vite_pidgey-base/`, `vite_pidgey/`.
- `source/public/` row: built frontend assets go under `admin/` (not `static/`), served at `/admin/`.

**`architecture/backend.md`**
- "Apache rewrites non-file requests" → "requests that are not existing files or directories".
- Static files line: the built frontend lives under `admin/` and is served at `/admin/` directly
  by Apache; `/admin/` is reserved for the UI (the API router never claims it).

**`overview/components.md`**
- Admin UI served at `/admin/` (diagram label / React frontend row).

## Files to Change
- `docs/agents/specs/deployment/pipeline.md`
- `docs/agents/specs/architecture/repository-layout.md`
- `docs/agents/specs/architecture/backend.md`
- `docs/agents/specs/overview/components.md`
