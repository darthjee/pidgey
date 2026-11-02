# Verify

Check that no spec still describes the UI at `/admin/` and that links resolve.

- `grep -rn "/admin/" docs/agents/specs | grep -v "/admin/users"` returns nothing that refers to the UI (API paths under `/admin/users/*.json` and hash routes like `#/admin/users` are fine).
- `grep -rn "public/admin" docs/agents/specs` returns nothing.
- Every relative link added or changed (e.g. to `request-handler/static-files.md`) points at an existing file and heading.
- No "Pending decision" remains on points decided in #120/#121.

## Files to Change

- None (verification only).
