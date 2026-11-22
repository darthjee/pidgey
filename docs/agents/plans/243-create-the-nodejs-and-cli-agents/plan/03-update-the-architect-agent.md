# Update the architect agent

Route client work to the new agents and record who owns what around `clients/`.

- **Your scope:** add `clients` to the parenthesised list of area docs owned by `product-owner` (so `docs/agents/clients.md` and `docs/agents/clients/` are not architect's); add "files directly under `clients/`" to the paths architect owns.
- **Specialist agents table:** add `nodejs` → `clients/nodejs/` — library `pidgey-client` (zero-dependency npm package, Jasmine, c8); `cli` → `clients/cli/` — CLI `pidgey-cli`, thin layer over the library; and extend the `infra` row with `clients/scripts/`.
- **How to coordinate:** add the client rules: a change spanning both packages is one PR, `nodejs` dispatched before `cli`; a `/v1` OpenAPI change by `backend` is followed in the same PR by `nodejs` regenerating `types/`; a CLI agent report that it needs a library change is routed to `nodejs`; only the scaffold issues (#246, #249) create a package folder.

## Files to Change
- `.claude/agents/architect.md` — scope list, agents table, coordination rules.
