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

## One database user

Migrations and the app use the same database user, which therefore holds DDL rights (`CREATE`,
`ALTER`, `INDEX`, `REFERENCES`) at runtime. The host supports a second user, so a
least-privilege split (DDL for migrations only, data access only for the app) is possible; it
is tracked in #109.

## No HSTS header

Neither the app nor the host sends `Strict-Transport-Security`. The host redirects HTTP to
HTTPS, but a first plain-HTTP request is still possible. Tracked in #110.

## Every path returns `200 {"status":"ok"}`

The router does not exist yet: `public/index.php` answers every request with the health
handler, so any path (not only `/health.json`) returns `200 {"status":"ok"}`. A `200` from
`/health.json` after a release therefore proves only that PHP serves the live release. The
router with a `404` fallback is tracked in #111.

## `TRUSTED_PROXY_HOPS` not verified

The shared `.env` sets `TRUSTED_PROXY_HOPS=0` because no reverse proxy sits in front of Apache
on this host (see [server.md](server.md#shared-env)). That cannot be checked from outside until
an endpoint shows the client IP the app resolved. If a proxy turns out to be there, the value
is wrong and clients behind it all share the proxy's IP.

## No `cleanup` action

`darthjee/scripts:0.11.0` has no `cleanup` action; it arrives in `0.12.0`. Until the CI image
is bumped (see [pipeline.md](pipeline.md#deploy-script)), staging folders left by failed
workflows are removed by hand (see
[operations.md](operations.md#removing-stale-staging-folders)).
