# Cross-link from related specs

Point the specs that mention the rewrite in passing at `rewrite.md`, without moving content out of them.

- `docs/agents/specs/architecture/backend.md`, section **Entry point**: in the sentence "Apache rewrites requests that are not existing files or directories to it", link the rewrite to [request-handler/rewrite.md](../request-handler/rewrite.md). Keep the three routing rules (`*.json` always to the API, `/admin/` reserved for the UI, only `POST /v1/messages` prefixed) here, as agreed in #56.
- `docs/agents/specs/frontend/build.md`, the **Pending decision — `/admin/` serving** paragraph: add a link to [request-handler/rewrite.md](../request-handler/rewrite.md) as the place where the bypass rules live. Do not resolve the pending decision here.
- `docs/agents/specs/request-handler.md`: no change expected. The flow diagram and the `rewrite.md` table row stay accurate. Only touch it if the written spec changes the flow.

## Files to Change
- `docs/agents/specs/architecture/backend.md` — add a link to `rewrite.md` in **Entry point**.
- `docs/agents/specs/frontend/build.md` — add a link to `rewrite.md` in the `/admin/` serving pending decision.
