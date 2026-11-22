# Delete the specs and update the docs hub

Delete the spec hub and the spec folder. Then, in the docs hub `docs/agents/specs.md`:

- Remove the `node-client` row of the `## Specs` table.
- Replace the now-empty table, header row included, with the single line `No spec in progress.`
- Keep the `## Specs` heading and its intro text ("Features not built yet. Each spec is temporary: …").
- Keep the hub's intro sentence about specs ("This hub also indexes the specs of features not built yet …").
- Check that the `## Index` table has the `clients` row added by #246. Add it if it is missing.

## Files to Change
- `docs/agents/specs/node-client.md`: delete.
- `docs/agents/specs/node-client/`: delete the whole folder (six files).
- `docs/agents/specs.md`: drop the `node-client` row, use the "No spec in progress." line, and make sure the `clients` row exists.
