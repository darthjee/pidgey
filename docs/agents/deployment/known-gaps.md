# Known gaps

Purpose: list what the current deployment does not cover yet, why, and where each gap is
tracked.

## No proxy rate limiting

The public auth endpoints (`/auth/*.json`: login, register, recover, reset-password) have no
application-level throttle. Deployments behind a configurable reverse proxy should rate-limit
`/auth/*.json` per client IP there, to slow down password guessing and registration abuse.

The current host is a shared host with no configurable reverse proxy, so this cannot be
applied. To limit registration abuse, production keeps `USER_APPROVAL_REQUIRED=true` (see
[server.md](server.md#shared-env)).

Application-level throttling is an open decision owned by
[api/pending.md](../specs/api/pending.md) (rate limits).

## Migration credentials in the shared `.env`

`DB_MIGRATE_USER` / `DB_MIGRATE_PASSWORD` live in the same shared `.env` as the app settings
(see [server.md](server.md#shared-env)). `public/index.php` loads that file on every request,
so the web process has the migration user's credentials in its environment. The split
between the two users stops SQL issued by the app (e.g. SQL injection) from altering or
dropping the schema. It does not protect against a file-read or code-execution flaw in the
web app, which could read those credentials. Keeping them in a separate file that only the
`bin/` schema commands load would close this; it is not tracked yet.

## `TRUSTED_PROXY_HOPS` not verified

The shared `.env` sets `TRUSTED_PROXY_HOPS=0` because no reverse proxy sits in front of Apache
on this host (see [server.md](server.md#shared-env)). That cannot be checked from outside until
an endpoint shows the client IP the app resolved. If a proxy turns out to be there, the value
is wrong and clients behind it all share the proxy's IP.
