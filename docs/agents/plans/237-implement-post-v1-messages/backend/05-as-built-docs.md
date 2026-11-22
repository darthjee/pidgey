# As-built docs

Sync the docs with what was built, in the same change set:

- `docs/agents/api/endpoints/sending.md`: remove the "Depends on #237 ..." note under `### Idempotency-Key`.
- `docs/agents/api/authentication/api-tokens.md`: replace "`last_used_at` is updated on successful use only" with "updated on a successful authentication, including when the scope check then answers `403`". `sending.md` gets the same wording, if it needs it.
- `docs/agents/request-handler/middlewares.md` and `docs/agents/request-handler/routing.md`: replace the "illustrative" class names with the real ones (handler name, `routes/v1.php`), if they differ.
- `docs/agents/architecture/backend.md`: mention the new classes, if it lists handlers and middlewares.

## Files to Change
- `docs/agents/api/endpoints/sending.md`
- `docs/agents/api/authentication/api-tokens.md`
- `docs/agents/request-handler/middlewares.md`
- `docs/agents/request-handler/routing.md`
- `docs/agents/architecture/backend.md`
