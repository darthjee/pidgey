# Add the planned clients/ rows to the layout docs

Add `clients/` to both layout docs, marked *(planned)* (the convention `folder-structure.md` already uses for `dev/` and `dockerfiles/pidgey/`).

- `docs/agents/folder-structure.md`, Project Root table: a `clients/` row *(planned)*: "Client packages: `nodejs/` (library `pidgey-client`, owner `nodejs`), `cli/` (CLI `pidgey-cli`, owner `cli`), `scripts/` (shared check, integration and publish scripts, owner `infra`). Files directly under `clients/` belong to `architect`."
- `docs/agents/architecture/repository-layout.md`: the same `clients/` row *(planned)*, after `frontend/`, without links to `docs/agents/clients*` (those docs do not exist until #246).

## Files to Change
- `docs/agents/folder-structure.md` — `clients/` row.
- `docs/agents/architecture/repository-layout.md` — `clients/` row.
