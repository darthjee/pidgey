# Verify

There is no Markdown CI, so check by hand:

1. `grep -n "Pending decision" docs/agents/specs/request-handler/rewrite.md` returns nothing (the stub marker is gone and nothing is left open).
2. `head -3 docs/agents/specs/request-handler/rewrite.md` shows `# Apache Rewrite` and the unchanged `Purpose:` line from #56.
3. Every relative link added in steps 01 and 02 resolves to an existing file (for example, resolve each `](...)` target from the file's own folder with `ls`).
4. The `.htaccess` block in `rewrite.md` matches the issue exactly, directive for directive and in the same order.
5. `git diff --stat` touches only `docs/agents/specs/request-handler/rewrite.md`, `docs/agents/specs/architecture/backend.md` and `docs/agents/specs/frontend/build.md`. No file under `source/`, `dockerfiles/`, `docs/agents/specs/docker/` or `docs/agents/specs/deployment/` changes.

## Files to Change
- None (verification only).
