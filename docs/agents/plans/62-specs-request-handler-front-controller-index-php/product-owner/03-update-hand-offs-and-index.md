# Resolve the hand-offs in handlers, request-response and the index

- `handlers.md`: `## Hand-offs` #62 entry marked resolved, linking `front-controller.md`; the dispatcher reads the global list from `Router::globalMiddlewares()`. Replace loose "(#62)" mentions with links to the right `front-controller.md` section.
- `request-response.md`: the #62 hand-off marked resolved, linking `front-controller.md#error-wrapper` (or the actual anchor).
- `request-response/errors.md`, `request-response/response.md`, `request-response/processing-request.md`: "(#62)" mentions become links to `front-controller.md` sections where useful; no rule changes.
- `docs/agents/specs/request-handler.md` (index): the flow diagram and the `front-controller.md` row mention `FrontController`, the CORS step and request logging.

## Files to Change
- `docs/agents/specs/request-handler/handlers.md`
- `docs/agents/specs/request-handler/request-response.md`
- `docs/agents/specs/request-handler/request-response/errors.md`
- `docs/agents/specs/request-handler/request-response/response.md`
- `docs/agents/specs/request-handler/request-response/processing-request.md`
- `docs/agents/specs/request-handler.md`
