# Endpoints

Purpose: list the API v1 endpoints and their contracts.

## Sending

### `POST /v1/messages`

Auth: Bearer token with scope `send`.

Request:
```json
{ "to": "user@example.com", "subject": "Hello", "text": "Hi!", "html": "<p>Hi!</p>" }
```

Success `200`:
```json
{ "status": "sent", "smtp": { "code": 250, "message": "OK: queued as ..." } }
```

Failure: error body as in [conventions/errors.md](conventions/errors.md), codes from
[email/failures.md](../email/failures.md).

## Admin endpoints (used by the admin UI)

Auth: PHP session cookie, or Bearer token with scope `admin`.
All operate on the caller's organization.

| Method & path | Purpose |
| --- | --- |
| `POST /v1/registration` | Bootstrap: create organization + first user, start session (no auth). |
| `POST /v1/session` | Log in (no auth). |
| `DELETE /v1/session` | Log out. |
| `GET /v1/session` | Current user and organization. |
| `GET /v1/organization` / `PATCH /v1/organization` | Read / update organization. |
| `GET /v1/smtp_connections` / `POST /v1/smtp_connections` | List / create SMTP connections. |
| `GET/PATCH/DELETE /v1/smtp_connections/{id}` | Read / update / delete a connection. |
| `GET /v1/api_tokens` / `POST /v1/api_tokens` | List / create tokens. Creation returns the plain token **once**. |
| `DELETE /v1/api_tokens/{id}` | Revoke a token. |

Session endpoints are session-only (cannot be used with Bearer tokens).

**Pending decisions:** final paths (working assumption above); user management
endpoints (depends on [organizations/entities.md](../organizations/entities.md));
a "send test email" endpoint for a connection.
