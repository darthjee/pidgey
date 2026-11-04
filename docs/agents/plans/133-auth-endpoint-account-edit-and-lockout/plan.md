# Plan: Auth endpoint: account edit and lockout

Issue: [133-auth-endpoint-account-edit-and-lockout.md](../../issues/133-auth-endpoint-account-edit-and-lockout.md)

## Overview
Adds the gated `PATCH /auth/account.json` (edit own username, email, password with `currentPassword`) and its failed-attempt lockout, per `docs/agents/specs/api/endpoints/auth/account.md`. Backend only.

See [backend.md](backend.md) for the full plan.
