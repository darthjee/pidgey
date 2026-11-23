# Hand-off from #243

These files are owned by `architect`; keep the edits to the hand-off.

- `.claude/agents/nodejs.md`:
  - replace the *(planned)* line with the exact commands:
    - `make clients-checks`;
    - `docker compose run --rm clients clients/scripts/checks.sh`;
    - `docker compose run --rm clients sh -c "cd clients/nodejs && yarn lint"` (and `typecheck`, `test`, `coverage`);
  - point the "as-built" reference at the now-existing `docs/agents/clients/nodejs.md` (drop "planned: #246").
- `docs/agents/folder-structure.md` and `docs/agents/architecture/repository-layout.md`:
  - drop the *(planned)* marker of `clients/`;
  - keep `cli/` marked as coming in #249, so `clients/`, `clients/nodejs/` and `clients/scripts/` read as existing.

## Files to Change
- `.claude/agents/nodejs.md`: the local commands, and the as-built doc link.
- `docs/agents/folder-structure.md`: the `clients/` row.
- `docs/agents/architecture/repository-layout.md`: the `clients/` row.
