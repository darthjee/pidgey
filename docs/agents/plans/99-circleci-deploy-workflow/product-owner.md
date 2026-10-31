# Product-owner Plan: CircleCI deploy workflow

Main plan: [plan.md](plan.md)

## Shared contracts

- New `pidgey-deploy` context variable `SHARED_DIR`: the shared folder's name, relative to `$REMOTE_HOME`. It is required, kept private and not a secret. The shared `.env` path is `$REMOTE_HOME/$SHARED_DIR/.env`.

## Implementation Steps

### Step 1 — Record `SHARED_DIR` in the deployment specs

- Add a `SHARED_DIR` row to the CircleCI variables table in `release.md`, next to `REMOTE_HOME`: "Name of the shared folder, relative to `REMOTE_HOME`", Required: Yes, Secret: No (kept private).
- Replace every `<shared folder>` placeholder with `$SHARED_DIR`:
  - `release.md` (release step 1);
  - `server.md` (folders table and the `config/.env` link section).
- In `server.md`'s "never committed" paragraph, add `SHARED_DIR` to the list of variable names and drop `<shared folder>` from the placeholder examples, keeping `<live>`.
- Check `security.md` and `pipeline.md` for variable lists that should mention `SHARED_DIR`. `security.md` lists `SSH_*`, `REMOTE_HOME` and `PHP_BIN`, so add it there.

## Files to Change

- `docs/agents/specs/deployment/release.md`: variables table and release step 1.
- `docs/agents/specs/deployment/server.md`: placeholder replaced by `$SHARED_DIR`, and the variable added to the "never committed" list.
- `docs/agents/specs/deployment/security.md`: `SHARED_DIR` added to the context variable list.

## Notes

- Docs only; no CI job checks it.
