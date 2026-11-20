# Public OpenAPI route in openapi.md and testing.md

Replace the `**Pending decision** (#225)` marker under `## GET /v1/openapi.json` in `openapi.md`:

- **Stays public:** it reveals nothing beyond what the `POST /v1/messages` error envelope and the npm package already reveal; requiring a token would protect nothing secret and break tooling.
- **Static committed file:** a new short section (or table rows) stating the `/v1` document is a file committed in the repository and served verbatim; the CI breaking-change diff and the client's type generation read that same file. Never generated at runtime from the router. Its location in the backend tree is left to the implementation sub-issue.
- **Content rules** (enforced by the route's PHPUnit test): only `/v1` paths; no `servers` entry with a real host (omitted or the relative `/`); `info.version` is the contract version (for example `1.0.0`), never the Pidgey release version; examples use `pdg_example…` tokens and `example.com` hosts only; the Bearer security scheme is described without any detail of token storage or hashing. Tighten the existing `Content` row of the table to point at these rules instead of duplicating them.
- **Caching:** a `Cache headers` row: none, following `../../api/conventions/format.md#cache-headers`, like `/health.json`; the client never fetches the document at runtime. Rejected: `no-cache`, `max-age` + `ETag`.
- **Credentials and load:** the route ignores credentials (an invalid Bearer is served normally, like the operational probes in `../../api/endpoints/operational.md`); it reads a static file and touches no database; no route-specific rate limit (the global pending item in `../../api/pending.md` applies).

In `testing.md`, extend the "Backend, OpenAPI route" bullet with the content-rule assertions (every path starts with `/v1`, no `servers` host, `info.version` is not the Pidgey release version) and "no cache headers; an invalid Bearer is still served".

## Files to Change
- `docs/agents/specs/node-client/openapi.md` — route rules replace the #225 marker.
- `docs/agents/specs/node-client/testing.md` — route test bullet.
