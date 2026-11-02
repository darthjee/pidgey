# Plan: Gate middleware and shared `user` payload

Issue: [130-gate-middleware-and-shared-user-payload.md](../../issues/130-gate-middleware-and-shared-user-payload.md)

## Overview
Backend-only work. It wires the dispatcher plumbing the specs already define but the code lacks:
- `build(Container)` on handlers and middlewares, plus `HandlerFactory`;
- one lazy `Container` per request;
- `ProcessingRequest` attachments;
- `UnauthorizedException`.

On top of that it adds `Caller` and `AuthenticatedUser`, the one-query session lookup, the `Gate` and `AdminCheck` middlewares, `UserPayload::fromUser()`, and the deny-by-default and factory integration tests.

See [backend.md](backend.md) for the full plan.
