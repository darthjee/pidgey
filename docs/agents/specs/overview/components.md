# Component Map

Purpose: describe the high-level components of Pidgey and how they interact.

```
 API clients ──(HTTPS, Bearer token)──┐
                                      ▼
 Browser ──(access_token cookie)──► Apache + PHP (Pidgey backend) ──(PDO)──► MySQL 9
   ▲                              │
   │ static files (/, /assets/)   └──(SMTP: none/TLS/SSL)──► External SMTP servers
   └──────── React admin UI (built by Vite, served at / by the PHP static-file handler)
```

## Components

| Component | Responsibility |
| --- | --- |
| Apache + PHP backend | Serves the JSON API and the auth and admin endpoints through a single front controller; also serves the built frontend at `/` from `source/static/` through the PHP static-file handler. See [architecture/backend.md](../architecture/backend.md), [request-handler.md](../request-handler.md) and [request-handler/static-files.md](../request-handler/static-files.md). |
| React frontend | Admin UI built with Vite; its release build lives in `source/static/` and is served at `/` by the PHP static-file handler, with hash routing for deep links. See [frontend](../frontend.md). |
| MySQL 9 | Persists organizations, users, SMTP connections and API tokens. Accessed with raw PDO. See [architecture/database.md](../architecture/database.md). |
| External SMTP servers | Configured per organization; Pidgey connects to them synchronously to deliver each message. See [email](../email.md). |
| Mailpit (dev/test only) | Fake SMTP server used for local development and integration tests. |

There is no reverse proxy layer and no message queue.
