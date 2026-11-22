# Update the backend, frontend, infra and product-owner agents

Small, targeted edits; no other rule of these agents changes.

- `backend.md`: one line under the scope rules: a PR changing the `/v1` OpenAPI document also has `nodejs` regenerate the committed `types/` file in `clients/nodejs/`, coordinated by `architect`.
- `frontend.md`: `description` only, anchored to the admin UI, e.g. "Pidgey frontend specialist. Use for any task involving the React admin UI under frontend/: JavaScript/JSX, Vite, react-bootstrap, Jasmine, ESLint, Yarn. Not for the Node clients under clients/." The body is unchanged.
- `infra.md`: `description` gains "the client check, integration and publish scripts under clients/scripts/"; "You own" gains `clients/scripts/` (`checks.sh`, `integration.sh`, the staging-copy pack, the pack smoke test; the npm scripts they call belong to `nodejs` and `cli`); "Do NOT touch" gains `clients/nodejs/` (`nodejs`) and `clients/cli/` (`cli`).
- `product-owner.md`: its list of area docs gains `clients` (`docs/agents/clients.md` + `docs/agents/clients/`).

## Files to Change
- `.claude/agents/backend.md` — the OpenAPI → `types/` coordination line.
- `.claude/agents/frontend.md` — narrowed `description`.
- `.claude/agents/infra.md` — `description`, "You own", "Do NOT touch".
- `.claude/agents/product-owner.md` — area list.
