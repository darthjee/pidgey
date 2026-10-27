# Verify links and terminology

No CI job checks Markdown, so verify by hand from the repository root.

1. **Every relative link in a changed or new file resolves to an existing file.** For example, extract the `](...)` targets from each changed file and check each path, relative to that file's directory, with `test -e`.
2. **The stubs are in place and each points to its owner:**
   `grep -rn 'content to be specified in #' docs/agents/specs/request-handler/` returns exactly six lines (#57–#62).
3. **"Controllers" is gone from the HTTP-layer descriptions:**
   `grep -rn -i 'controllers' docs/agents/specs docs/agents/architecture.md` returns nothing that describes the HTTP layer. "front controller" is fine.
4. **The routing rules are still in `backend.md`:** it still states that existing files and directories bypass PHP, that `*.json` is always routed to the API, and the `/v1` prefix rule.
5. **Formatting:** every new file starts with `# Title` followed by a `Purpose:` line.

## Files to Change
- None (verification only).
