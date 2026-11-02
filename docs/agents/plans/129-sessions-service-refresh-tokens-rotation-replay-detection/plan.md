# Plan: Sessions service: refresh tokens, rotation, replay detection

Issue: [129-sessions-service-refresh-tokens-rotation-replay-detection.md](../../issues/129-sessions-service-refresh-tokens-rotation-replay-detection.md)

## Overview
Add `Pidgey\Auth\Sessions`, the single service that owns the refresh-token rules of `specs/api/authentication/sessions.md`. It covers start, rotation with replay detection, logout, bulk revoke, and the passive lookup for status. It is backend-only and adds no endpoints.

See [backend.md](backend.md) for the full plan.
