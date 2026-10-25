# Admin Endpoints

Purpose: temporary list of the Pidgey-only admin endpoints (organization, SMTP connections,
API tokens) used by the admin UI, until #31 rewrites them with the Citadel conventions.

Auth: PHP session cookie, or Bearer token with scope `admin`.
All operate on the caller's organization.

> **Superseded:** the auth model above is replaced by [authentication.md](../authentication.md):
> these endpoints use the cookie session only (no Bearer token, no `admin` scope). #31
> rewrites this file.

| Method & path | Purpose |
| --- | --- |
| `GET /v1/organization` / `PATCH /v1/organization` | Read / update organization. |
| `GET /v1/smtp_connections` / `POST /v1/smtp_connections` | List / create SMTP connections. |
| `GET/PATCH/DELETE /v1/smtp_connections/{id}` | Read / update / delete a connection. |
| `GET /v1/api_tokens` / `POST /v1/api_tokens` | List / create tokens. Creation returns the plain token **once**. |
| `DELETE /v1/api_tokens/{id}` | Revoke a token. |

Registration, login, logout and the current user's state are served by the auth endpoints
([auth.md](auth.md)); instance-admin user management (`/admin/users/*.json`) by
[admin-users.md](admin-users.md); the current organization is read through `GET /organization.json`
(#31).

## Pending decisions

- **Pending decision:** final paths (working assumption above; #31).
- **Pending decision:** a "send test email" endpoint for a connection (#31).
