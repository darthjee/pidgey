# Re-point markers to #231, hub table, consistency pass

1. Re-point the four markers not settled by #225 from `(#225)` to `(#231)`, wording unchanged:
   - `library.md`: cleartext `http://` (`## Base URL` or wherever it sits) and connection reuse / worst-case duration (`## Timeout`);
   - `errors-and-retry.md`: retained response body;
   - `cli.md`: stdin size cap (`## Body and stdin`).
2. In the hub `docs/agents/specs/node-client.md`, `## Pending decisions` table:
   - narrow the #225 row out entirely (its three topics are now decided) — remove the row;
   - add a #231 row: "Runtime security and limits (cleartext `http://`, retained response body, stdin size cap, connection reuse and worst-case duration)", markers in `library.md`, `errors-and-retry.md`, `cli.md`.
   - Keep the rows sorted by issue number.
3. Consistency pass:
   - `grep -rn '(#225)' docs/agents/specs/` returns nothing; every `(#231)` marker is listed in the hub row.
   - No decision is stated in two files (link instead).
   - Every relative link in the touched files resolves.
   - Examples use placeholders only; no `--token` remains anywhere.
   - Collect any conflict with as-built docs for the PR description.

## Files to Change
- `docs/agents/specs/node-client/library.md` — two markers re-pointed.
- `docs/agents/specs/node-client/errors-and-retry.md` — one marker re-pointed.
- `docs/agents/specs/node-client/cli.md` — stdin marker re-pointed.
- `docs/agents/specs/node-client.md` — pending-decisions table.
