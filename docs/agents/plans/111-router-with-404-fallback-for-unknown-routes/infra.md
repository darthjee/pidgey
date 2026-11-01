# Infra Plan: Router with 404 fallback for unknown routes

Main plan: [plan.md](plan.md)

## Shared contracts

- `source/config/` (with `routes.php` and `routes/operational.php`) is a new top-level path that ships with every release; it is not added to `.circleci/backend-exclude.txt`. The release `link` step keeps adding `config/.env` inside it.
- After release, unknown paths answer `404`; check with `curl -s -o /dev/null -w '%{http_code}' https://<domain>/does-not-exist.json`.

## Implementation Steps

### Step 1 — Close the known gap
Remove the "Every path returns `200 {"status":"ok"}`" section from `docs/agents/deployment/known-gaps.md` (and any index/summary line pointing to it). If another deployment doc (e.g. `operations.md`) says a `200` from `/health.json` only proves PHP serves the release, or describes post-release checks, add the `404` check command above to the post-release verification.

### Step 2 — Record that `config/` ships
- `docs/agents/deployment/pipeline.md` "Exclude list": add `config/` (route files) to the "Everything else under `source/` ships" list.
- `docs/agents/deployment/server.md`: in "The `config/.env` link", note that `config/` already exists in staging from the upload (it holds the route files) and the `link` step adds the `.env` symlink beside them. Check that `deploy_frontend.sh link` handles an existing `config/` folder (it creates the folder, then `ln -sfn`); record it in the notes if that can't be confirmed from the docs.
- No change to `.circleci/backend-exclude.txt`.

## Files to Change
- `docs/agents/deployment/known-gaps.md` — remove the entry.
- `docs/agents/deployment/pipeline.md` — add `config/` to the shipped list.
- `docs/agents/deployment/server.md` — `config/` now ships route files, `.env` link added beside them.
- `docs/agents/deployment/operations.md` — only if it has a post-release check to extend.

## Notes
- Doc-only; no CI or Docker change is expected.
