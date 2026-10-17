# Component Map

Purpose: describe the high-level components of Pidgey and how they interact.

```
 API clients ──(HTTPS, Bearer token)──┐
                                      ▼
 Browser ──(session cookie)──► Apache + PHP (Pidgey backend) ──(PDO)──► MySQL 9
   ▲                              │
   │ static files (dist/)         └──(SMTP: none/TLS/SSL)──► External SMTP servers
   └──────── React admin UI (built by Vite, served by Apache)
```

## Components

| Component | Responsibility |
| --- | --- |
| Apache + PHP backend | Serves the JSON API and the admin session endpoints through a single front controller; also serves the built frontend as static files. See [architecture/backend.md](../architecture/backend.md). |
| React frontend | Admin UI built with Vite; its `dist/` output is served by Apache. See [frontend](../frontend.md). |
| MySQL 9 | Persists organizations, users, SMTP connections and API tokens. Accessed with raw PDO. See [architecture/database.md](../architecture/database.md). |
| External SMTP servers | Configured per organization; Pidgey connects to them synchronously to deliver each message. See [email](../email.md). |
| Mailpit (dev/test only) | Fake SMTP server used for local development and integration tests. |

There is no reverse proxy layer and no message queue.
