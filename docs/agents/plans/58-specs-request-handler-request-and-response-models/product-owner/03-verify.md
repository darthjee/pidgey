# 03 — Verify

Manual checks. There is no CI job for docs.

- `grep -rn 'content to be specified in #58' docs/agents/specs/request-handler/` returns nothing.
- Every new file starts with `# <Title>` followed by a `Purpose:` line.
- Every relative link in the new and changed files resolves. Check this with a short shell loop over the `](...)` targets, or by reading each one.
- Compare each decision in the issue's Solution with the spec, and make sure none is missing or changed: method names, status codes, the 1 MiB limit, the response-hook order, the `WallClock` split and the hand-off lines.
- `git diff --stat` touches only `docs/agents/specs/`. No PHP, tests or `.htaccess`.

## Files to Change

None (verification only).
