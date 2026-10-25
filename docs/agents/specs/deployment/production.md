# Production Notes

Purpose: record operational recommendations for running Pidgey in production that are not
enforced by the application.

## Rate limiting at the proxy

The public auth endpoints (`/auth/*.json`: login, register, recover, reset-password) have no
application-level throttle in v1 (see
[api/endpoints/auth/session.md](../api/endpoints/auth/session.md#brute-force) and the
rate-limit pending decision in [api/pending.md](../api/pending.md)). Deployments should rate
limit `/auth/*.json` per client IP at the reverse proxy in front of the app, to slow down
password guessing and registration abuse.

## Pending decisions

None. (Application-level throttling is pending in [api/pending.md](../api/pending.md).)
