# Frontend spec docs
- `api-compatibility.md`: the three `/api-tokens*` rows point at `ApiTokensClient` and become ✅; remove **M6** from the open mismatches (and the "API token endpoints are not…" sentence near line 70).
- `screens.md`: add `#/api-tokens` and `#/api-tokens/new` to the route table; the **Organization** dropdown lists Settings, SMTP connections, API tokens; add an "API tokens screens" section (list, revoked rows, revoke confirmation and `404`, create validation, the one-time token panel and the never-stored rule); remove the "Pidgey screens to come" table entry (and the section if it becomes empty).
- `layout.md`: add `ApiTokensClient` to the client table and `apiTokens/` to the resources folder example.

## Files to Change
- `docs/agents/specs/frontend/api-compatibility.md`
- `docs/agents/specs/frontend/screens.md`
- `docs/agents/specs/frontend/layout.md`
