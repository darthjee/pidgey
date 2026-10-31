# Related docs
Fix the references that are now stale. Do **not** delete or repoint links to the deployment specs; #102 does that.
- `docs/agents/contributing.md`:
  - drop *(planned)* from `frontend_checks`, `build_backend`, `build_frontend`, `upload_backend`, `upload_frontend` and `release`, and keep it on `build_images`;
  - reword the intro sentence so it no longer says the deploy jobs are planned;
  - point the `build_backend` row's link at `deployment/pipeline.md`.
- `AGENTS.md`: add a "Deployment" row to the documentation table, linking `docs/agents/deployment.md` ("How the production deployment works as implemented: pipeline, variables, server, operations, known gaps").

## Files to Change
- `docs/agents/contributing.md` — the CI jobs table and its intro.
- `AGENTS.md` — the documentation table.
