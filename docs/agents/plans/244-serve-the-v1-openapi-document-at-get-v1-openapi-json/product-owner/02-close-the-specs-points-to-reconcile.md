# Close the spec's Points to reconcile

In `docs/agents/specs/node-client/openapi.md`:
- record the decided path `source/openapi/v1.json` in Document and Static committed file;
- reword the CORS row to "no CORS of its own (the global `Cors` step applies as on the rest of `/v1`)";
- mark every item of "Points to reconcile with the as-built docs" as settled, each with a link to where it's now documented. State that the routing `404` is a single sentence in `info.description` and isn't listed on each operation.

## Files to Change
- `docs/agents/specs/node-client/openapi.md` — decisions recorded, the section marked done.
