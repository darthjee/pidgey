# 02 — Cross-links and index sync

Keep the index in sync, and point the specs that already describe these concerns at the new source of truth. Add links only: no rules are restated.

- `docs/agents/specs/request-handler.md`:
  - Update the `request-response.md` table row to mention the error responses and the folder.
  - Annotate the `→ response hooks` line of the diagram so it says the hooks also run after an early response (index sync rule: flow changes update the diagram).
- `docs/agents/specs/api/conventions/errors.md`: where the Envelope section says the backend has a single error formatter, link to `request-handler/request-response/errors.md`.
- `docs/agents/specs/api/authentication/sessions.md` (`## Clock`): state that PHP's UTC wall clock is read through `Pidgey\Infra\WallClock`, with a link.
- `docs/agents/specs/architecture/backend.md`, Layers table, Infrastructure row: add the wall clock to the list.

## Files to Change

- `docs/agents/specs/request-handler.md`: table row and diagram annotation.
- `docs/agents/specs/api/conventions/errors.md`: link to the error formatter.
- `docs/agents/specs/api/authentication/sessions.md`: `WallClock` link.
- `docs/agents/specs/architecture/backend.md`: Infrastructure row.
