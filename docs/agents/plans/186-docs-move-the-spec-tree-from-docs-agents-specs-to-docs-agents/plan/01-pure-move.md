# Pure move

Commit 1. Only `git mv`, no content edits, so every entry is a 100% rename.

From `docs/agents/`:

```bash
for a in overview persistence request-handler docker organizations email api frontend testing; do
  git mv specs/$a.md $a.md
  git mv specs/$a $a
done
git mv specs/architecture architecture
```

- `specs/architecture.md` stays in place (name collision with `docs/agents/architecture.md`; #187 merges it).
- `testing.md` + `testing/` land at `docs/agents/testing*` temporarily (#188 folds them into `contributing`).
- Afterwards `docs/agents/specs/` contains only `architecture.md`.
- Verify: `git status` shows only renames; `ls docs/agents/specs` → `architecture.md`.

Commit message: `docs(specs): move the spec tree up to docs/agents/` (repo commit template, agent `architect`).

## Files to Change
- `docs/agents/specs/{overview,persistence,request-handler,docker,organizations,email,api,frontend,testing}.md` → `docs/agents/<area>.md` — renamed.
- `docs/agents/specs/{overview,persistence,request-handler,docker,organizations,email,api,frontend,testing,architecture}/**` → `docs/agents/<area>/**` — renamed.
