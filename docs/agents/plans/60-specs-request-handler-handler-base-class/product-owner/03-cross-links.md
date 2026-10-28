# Index and backend architecture

- `docs/agents/specs/request-handler.md`: update the `handlers.md` row to "Request handler base class (`RequestHandler`): template method, `HandlerFactory` and `Container` shape, lifecycle, `intParam()`, body validation, errors."
- `docs/agents/specs/architecture/backend.md`: in "Wiring is done manually (a small container/factory class), no DI framework.", link the container/factory to `Pidgey\Container` and `HandlerFactory` in [request-handler/handlers.md](../request-handler/handlers.md). If the layers table lists the HTTP layer's classes, mention `RequestHandler` / `HandlerFactory` there briefly.

## Files to Change
- `docs/agents/specs/request-handler.md` — the index row.
- `docs/agents/specs/architecture/backend.md` — the container pointer.
