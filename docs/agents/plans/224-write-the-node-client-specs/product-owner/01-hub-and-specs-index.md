# Hub and specs index

Create the hub `docs/agents/specs/node-client.md`:
- Purpose: the Node.js library `pidgey-client` (`clients/nodejs/`) and the CLI `pidgey-cli` (`clients/cli/`, command `pidgey`) for `POST /v1/messages`, plus the `/v1` OpenAPI document; parent epic #219.
- Scope and non-goals (from #219's Scope section): other languages, browser target, CLI Docker/binaries, admin API, future message fields, backend changes beyond `GET /v1/openapi.json`.
- A `| File | Content |` table indexing the six topic files.
- A "Pending decisions" list linking every `**Pending decision**` marker to its file and issue (#220, #222, #223, #225, #226).
- A note that these specs are temporary and are removed by #229 once the as-built docs under `docs/agents/clients/` cover them.

Update `docs/agents/specs.md`: rewrite the intro sentence ("…there are none today") and add a `## Specs` section with a table (`| Spec | Purpose |`) holding one row for `docs/agents/specs/node-client.md`. Leave the area index table unchanged.

## Files to Change
- `docs/agents/specs/node-client.md` — new hub.
- `docs/agents/specs.md` — intro sentence and new `## Specs` table.
