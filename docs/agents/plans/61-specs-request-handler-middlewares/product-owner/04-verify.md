# Verify

- `grep -rn 'content to be specified in #61' docs/agents` returns nothing.
- `grep -rn '#61' docs/agents/specs` only shows resolved hand-offs (no "stays with #61", "decides", "should implement").
- `middlewares.md` starts with a title and a `Purpose:` line, is in English, has no PHP beyond short illustrative snippets, and does not redefine guard behaviour (it links to `origin-check.md`, `gate.md`, `api-tokens.md`).
- Every relative link in the changed files resolves. Check that each `](...)` target exists, including anchors.
- Each decision in the issue's Expected Behavior section appears in `middlewares.md`.

## Files to Change
- None (checks only).
