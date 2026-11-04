# Plan: Auth endpoints: refresh, logoff and status

Issue: [132-auth-endpoints-refresh-logoff-and-status.md](../../issues/132-auth-endpoints-refresh-logoff-and-status.md)

## Overview
Add the three remaining public session endpoints (`POST /auth/refresh.json`, `DELETE /auth/logoff.json`, `POST /auth/status.json`) as thin handlers over the `Sessions` service merged in #129, with integration tests for every row of the spec tables.

See [backend.md](backend.md) for the full plan.
