# Verify

- `grep -rnE '\{(id|uuid)\}' docs/agents/specs` leaves only non-API placeholders.
- `grep -rn 'content to be specified in #59' docs/agents` returns nothing.
- Every relative link in the changed files resolves. Check each `](...)` target exists, including anchors you added.
- `routing.md` starts with a title and a `Purpose:` line, is in English, and has no PHP beyond short illustrative snippets.
- Each decision in the issue's Expected Behavior section appears in `routing.md`.

## Files to Change
- None (checks only).
