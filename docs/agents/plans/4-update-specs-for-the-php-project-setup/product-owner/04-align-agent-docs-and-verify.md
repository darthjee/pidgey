# Align agent docs and verify
Fix the agent docs that summarize the specs, then run the acceptance checks.

- `docs/agents/architecture.md`: "plain PHP 8.4".
- `docs/agents/contributing.md`: "PHP 8.4" (line 3 only; leave the CI table as is).
- `docs/agents/folder-structure.md`: add `circleci_pidgey-base/` to the `dockerfiles/` row.
- Change other summaries only where they contradict the updated specs.
- Verify (outside `docs/agents/issues/` and `docs/agents/plans/`):
  - `grep -rn "8\.x" docs AGENTS.md` returns nothing;
  - `grep -n "gd" docs/agents/specs/docker/images.md` returns nothing;
  - the PHP version, web-root and registry items are no longer marked "Pending decision";
  - `docs/agents/specs.md` still indexes every spec.

## Files to Change
- `docs/agents/architecture.md` — PHP 8.4.
- `docs/agents/contributing.md` — PHP 8.4.
- `docs/agents/folder-structure.md` — CI image in `dockerfiles/`.
