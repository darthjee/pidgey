# API docs

Record the size limits in the API docs and close the pending item.

- `sending.md`: under `POST /v1/messages`, state the 1 MiB body limit (shared by every route, linking the body parser rules) and the payload length rules (subject at most 255 characters; no cap on `text`/`html`). Add the body-parser rows to the Errors table: `400 BAD_REQUEST` (malformed JSON or non-object body), `413 HTTP_413` (body over 1 MiB), `415` (not `application/json`). Add a testable rule for 255 accepted / 256 rejected. Drop "Size limits" from its Pending decisions sentence.
- `pending.md`: remove the "size limits" item (including the subject sub-bullets), since they are decided.
- `conventions/errors.md` / `conventions/format.md`: only if needed, add a link stating the 1 MiB value where `413` is described.

## Files to Change

- `docs/agents/api/endpoints/sending.md` — limits, error rows, testable rule, pending sentence.
- `docs/agents/api/pending.md` — remove the size-limits item.
- `docs/agents/api/conventions/errors.md` — optional 1 MiB mention on the `413` row.
