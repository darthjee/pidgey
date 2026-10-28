# Cross-links and index

- `docs/agents/specs/request-handler.md`: keep the `routing.md` table row accurate, mentioning route registration, `:param` matching, the 404 fallback and the route files. Keep the flow diagram line `route match, :params, 404`.
- `docs/agents/specs/api/conventions/format.md` (`## Paths`): add one line saying path params are written `:name` and linking to `request-handler/routing.md`.
- `docs/agents/specs/api/conventions/errors.md`: where it says unknown routes get a 404 `Cannot POST /foo.json`, add that a wrong method on a known path gets the same 404, with a link to `routing.md`.
- `docs/agents/specs/request-handler/rewrite.md`: check that the existing `routing.md` links (for `/index.php` and `%2F`) still point to the right content. Add anchors if a heading is used.

## Files to Change
- `docs/agents/specs/request-handler.md` — table row.
- `docs/agents/specs/api/conventions/format.md` — `:name` notation line plus link.
- `docs/agents/specs/api/conventions/errors.md` — the wrong-method sentence.
- `docs/agents/specs/request-handler/rewrite.md` — fix links only if needed.
