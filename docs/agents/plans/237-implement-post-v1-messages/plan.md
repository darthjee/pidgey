# Plan: Implement POST /v1/messages

Issue: [237-implement-post-v1-messages.md](../../issues/237-implement-post-v1-messages.md)

## Overview

This plan implements the public sending API, `POST /v1/messages`, as it is already documented:
- the `ApiTokenAuth` Bearer middleware;
- the `RequireScope`/`RequireSendScope` scope check;
- the message payload validation;
- `routes/v1.php`;
- a handler that connects the existing `IdempotencyKeys` service and `SendFlow`.

All the work is in `source/`, plus the as-built doc sync.

See [backend.md](backend.md) for the full plan.
