# Consistency pass

Review the hub and the six files together:
- Every decision of #219 appears in exactly one spec file (no duplication across files; cross-link instead).
- Every `**Pending decision** (#N)` marker uses the agreed format and is listed in the hub.
- All relative links resolve (hub ↔ files, files → `docs/agents/api/`, `docs/agents/email/`, `docs/agents/contributing.md`).
- Examples use only placeholder tokens and `example.com`.
- Collect any conflict found between #219 and the as-built docs into a list for the PR description; the as-built docs win for current behaviour.

## Files to Change
- `docs/agents/specs/node-client.md` and `docs/agents/specs/node-client/*.md` — fixes from the review only.
