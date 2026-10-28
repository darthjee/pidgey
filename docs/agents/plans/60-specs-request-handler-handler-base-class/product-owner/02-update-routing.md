# Update routing.md

Bring `docs/agents/specs/request-handler/routing.md` in line with the #60 decisions:
- **Param values:**
  - replace the bullets that say handlers reject malformed values with `NotFoundException` (and the "This conflicts with the endpoint specs" sub-bullet) with: a malformed integer id gets `400 BAD_REQUEST` `Validation failed (numeric string is expected)` from `intParam()`;
  - an overflowing one gets the handler's `404`;
  - link `handlers.md`.
  - Keep "the router only checks `[^/]+`" and the rejection of `:id(\d+)`.
- Replace the `intParam('id')`-style bullet ("Where it lives is decided in #60") with a link to the `handlers.md` section.
- Point the `is_subclass_of` and factory mentions at the `RequestHandler` and `HandlerFactory` sections of `handlers.md` (keep `#60` only where it helps).
- **Hand-offs, the #60 bullet:** mark it as resolved, with links to the relevant `handlers.md` sections.
- **Pending decisions:** remove the "status for a malformed `:id`" item. If nothing remains, write "None."

## Files to Change
- `docs/agents/specs/request-handler/routing.md` — resolve the pending item and update the #60 pointers.
