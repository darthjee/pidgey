# Node client specs and consistency

Align the node-client specs with the decided limit, then check consistency.

- `cli.md`: stdin cap 10 MiB → **1 MiB** everywhere (the Stdin size cap section, the exit-code `2` row); replace "If the API limit (#233) ends up below 10 MiB, the CLI cap follows it" with the decision (the cap equals the API body limit; a body under 1 MiB whose JSON encoding exceeds it gets the API's `413`, exit `1`).
- `testing.md`: the `Body_spec.js` and `Pidgey_spec.js` rows (exactly 1 MiB accepted, 1 MiB + 1 byte → exit `2`).
- `openapi.md`: in "Documented statuses", state that the document includes the body-parser statuses (`400 BAD_REQUEST`, `413 HTTP_413`, `415`), matching the sending.md table.
- `errors-and-retry.md`: if it lists `413`, make sure it is not retried and matches `HTTP_413`.
- Consistency: `grep -rn '10 MiB\|#233\|size limits' docs/agents` must leave no stale reference.

## Files to Change

- `docs/agents/specs/node-client/cli.md` — 1 MiB cap.
- `docs/agents/specs/node-client/testing.md` — cap in the spec rows.
- `docs/agents/specs/node-client/openapi.md` — documented statuses.
- `docs/agents/specs/node-client/errors-and-retry.md` — only if `413` needs alignment.
