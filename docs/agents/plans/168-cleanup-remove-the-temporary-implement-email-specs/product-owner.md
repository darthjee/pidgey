# Product Owner Plan: Cleanup: remove the temporary implement-email specs

Main plan: [plan.md](plan.md)

## Implementation Steps

### Step 1 — Verify the open items
Three items in `docs/agents/specs/implement-email/` are unticked. Check each against the code
and tick it if done; if something is genuinely missing, spin off a follow-up issue (do not drop it):
- `encryption.md` item 7 (endpoint and transport use).
- `endpoints.md` `SMTP_TEST_MAX_ATTEMPTS` / `SMTP_TEST_WINDOW` (#166) — `ConfigValidator` already
  knows both keys; confirm the docs/`.env` examples list them.
- `frontend.md` item 7 (manual check against the development mailpit) — cannot be verified
  from code; record it as a follow-up issue or confirm with the user.

### Step 2 — Preserve what is still useful
Fold the useful rules of `implement-email/testing.md` (CI/test infrastructure) into the permanent
`docs/agents/specs/testing/` (and `specs/email/testing.md` if not already covered). Skim the other
`implement-email/*.md` files for content not yet in permanent specs (`architecture`, `email`, `api`).
Repoint `docs/agents/deployment/pipeline.md:33` to the permanent location.

### Step 3 — Delete the temporary specs
Remove `docs/agents/specs/implement-email.md`, the `docs/agents/specs/implement-email/` folder, and the
`implement-email` row in `docs/agents/specs.md` (line 28). Fix any link left dangling.

### Step 4 — Verify
`grep -rn "implement-email" docs/agents --exclude-dir=issues --exclude-dir=plans` returns nothing.

## Files to Change
- `docs/agents/specs/implement-email.md`, `docs/agents/specs/implement-email/*` — deleted
- `docs/agents/specs.md` — remove hub row
- `docs/agents/specs/testing/*` (or `specs/email/testing.md`) — receive migrated rules
- `docs/agents/deployment/pipeline.md` — repoint link

## Notes
- Documentation only; no CI checks apply.
- `issues/` and `plans/` may keep the name as history.
