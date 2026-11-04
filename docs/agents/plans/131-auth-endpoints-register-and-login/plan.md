# Plan: Auth endpoints: register and login

Issue: [131-auth-endpoints-register-and-login.md](../../issues/131-auth-endpoints-register-and-login.md)

## Overview
Backend only. Adds `POST /auth/register.json` and `POST /auth/login.json` on top of the sessions service from #129 (`Sessions::start()`), following `docs/agents/specs/api/endpoints/auth/session.md` and the shared rules of `docs/agents/specs/api/endpoints/auth.md`.

See [backend.md](backend.md) for the full plan.
