# Endpoints

Purpose: index of the endpoint specs: the public sending API, the auth endpoints and the
admin endpoints.

Admin and auth endpoints follow the Citadel contract (root-relative `*.json` paths, no `/v1`
prefix, cookie session); the public sending API keeps its `/v1` prefix and Bearer tokens (see
[conventions.md](conventions.md) and [authentication.md](authentication.md)).

| File | Content |
| --- | --- |
| [sending.md](endpoints/sending.md) | `POST /v1/messages`: the public sending API (Bearer token, scope `send`). |
| [auth.md](endpoints/auth.md) | `/auth/*.json`: register, login, refresh, logoff, status, password recovery, account edit, device authorization. |
| [admin.md](endpoints/admin.md) | Organization, SMTP connection and API token endpoints (temporary, rewritten by #31). |

## Pending decisions

Each detail file lists its own.
