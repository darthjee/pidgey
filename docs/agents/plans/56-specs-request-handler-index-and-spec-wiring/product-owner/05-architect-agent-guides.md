# (architect) Update the agent guides

**Owner: architect.** These files are outside `product-owner`'s scope ("the other `docs/agents/` guides"), so the architect applies this step directly.

- `docs/agents/architecture.md`, the `Pidgey\Http\` row of the namespace table (currently `Request/Response, router, controllers, JSON encoding, cookie-session gate and Bearer-token auth middleware. No SQL, no SMTP.`): replace it with wording that uses **request handlers** and **middlewares**, e.g. `Request/Response, router, request handlers, middlewares (cookie-session gate, Bearer-token auth), JSON encoding. No SQL, no SMTP. See [specs/request-handler.md](specs/request-handler.md).`
- `docs/agents/folder-structure.md`, the `public/` row (currently "… `index.php` (currently a placeholder stub returning `{"status":"ok"}`, replaced by the front controller later) …"): keep the "currently a placeholder stub" wording, and link "the front controller" to `specs/request-handler.md`.

Check the relative link paths against each file's location (`docs/agents/`).

## Files to Change
- `docs/agents/architecture.md` — terminology fix and a link in the `Pidgey\Http\` row.
- `docs/agents/folder-structure.md` — link the front controller to the new spec.
