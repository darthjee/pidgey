# OpenAPI spec

Write `docs/agents/specs/node-client/openapi.md` from #219's "OpenAPI" and "Compatibility and versioning" (`/v1` part):
- OpenAPI 3.1 document of `/v1` (`POST /v1/messages`, error envelope with `details`/`smtp`, Bearer scheme with `send` scope); static file in the backend tree owned by `backend`, path decided by its implementation sub-issue.
- `GET /v1/openapi.json`: public, JSON, `GET`/`HEAD` only, no CORS, no organization data.
- `/v1` additive-only rule and the breaking-change diff against `main` in backend CI.
- Client use: generated schema types (committed), `.d.ts` built at pack time from JSDoc, contract tests; the client never calls the route at runtime.
- `docs/agents/api/` remains the narrative contract and is updated in the same change set as the document (by the implementation sub-issue, not here).
- `**Pending decision** (#225)` for caching headers and exposure review of the public route.

## Files to Change
- `docs/agents/specs/node-client/openapi.md` — new.
