# .gitignore and spec updates

1. `.gitignore`:
   - Add `source/public/admin/`. This is the interim rule from `docs/agents/specs/frontend/build.md`: the release build stays untracked until the production decision is made.
   - Remove the citadel-only `backend/coverage/` and `backend/report/` lines.
2. `docs/agents/specs/docker/makefile.md`: in the `tests`, `fe-install` and `build-fe-release` rows, write the commands as `docker compose run --rm --no-deps frontend_dev ...`. Add a note that one-off frontend commands use `--no-deps`, so they don't start `app`/`mysql`.
3. `docs/agents/specs/docker/images.md`: in the `vite_pidgey-base`/`vite_pidgey` rows (or just below the table), say that the repo-root context is trimmed by the root `.dockerignore` (excluding `docker_volumes/`, `.git`, `node_modules`, build and coverage output).

Check the README First run block against the final targets. It was already updated in #16, so this should need no change.

## Files to Change

- `.gitignore` — add `source/public/admin/`, drop the `backend/` lines.
- `docs/agents/specs/docker/makefile.md` — `--no-deps` in the frontend rows.
- `docs/agents/specs/docker/images.md` — root `.dockerignore` note.
