# Verify links, frontmatter and routing

- Every relative link added in steps 01–05 resolves.
- `nodejs.md` and `cli.md` have valid frontmatter (`name`, `description`, `tools`), and `name` matches the file name.
- Routing check: match these titles against every agent `description` and confirm the owner: "Implement the pidgey-client retry policy" → `nodejs`; "Implement the pidgey CLI stdin cap" → `cli`; "Add the clients_checks job" → `infra`; "Fix the admin login screen" → `frontend`; "Serve the /v1 OpenAPI document" → `backend`.
- Nothing listed under the issue's Scope / Out changed (`AGENTS.md`, `docs/agents/specs.md`, `docs/agents/contributing.md`, the body of `frontend.md`, no file under `clients/`).

## Files to Change
- None (verification only).
