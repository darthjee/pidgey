# Operations
Create `docs/agents/deployment/operations.md`, as runbooks:
- **Release:** tag a green commit on `main` with `X.Y.Z` (no `v`), push the tag, then watch the workflow. Verify that `/health.json` returns 200, `/admin/` loads and the migrations are applied.
- **Rerun:** "Rerun from failed" keeps the workspace id, so it refills the same staging folder; `upload`, `link` and `migrate` are safe to repeat. A rerun or older tag redeploys older code, which works because of the expand/contract migration rule.
- **Failure before the swap** (`check-config` or `migrate`): the live release is untouched. Fix the problem and push a new tag. A failed migration is fixed with a new migration.
- **Broken swap:** the manual recovery over SSH is `mv <live>_old_<ts> <live>`. Explain how to tell which state the folders are in.
- **Stale staging folders:** `0.11.0` has no `cleanup`, so after a failed deploy, remove `${SSH_REMOTE_TEMP_DIR}-*` folders by hand. Never remove one that belongs to a running workflow.
- **Rotate the deploy key:** generate a new ed25519 key, add it to `authorized_keys` with `restrict`, update `SSH_PRIVATE_KEY` in the context, deploy, then remove the old key.
- **Rotate `SECRET_KEY`:** edit the shared `.env` (at least 32 bytes). The change takes effect on the next request, with no deploy needed. Note what it invalidates, according to `specs/architecture/configuration.md` and the auth specs (sessions and tokens).
- **Rotate the host key:** if the host key changes, re-run `ssh-keyscan`, check the fingerprint and update `SSH_KNOWN_HOSTS`.

## Files to Change
- `docs/agents/deployment/operations.md` — new.
