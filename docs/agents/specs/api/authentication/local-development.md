# Local Development

Purpose: explain how the `Secure` `access_token` cookie works in local development without
TLS, and what production requires.

## No TLS in development

- Development runs over plain HTTP, and there is **no switch** that turns off `Secure`. The
  `access_token` cookie has the same attributes in every environment
  (`HttpOnly; Secure; SameSite=Strict; Path=/`, see
  [access-token.md](access-token.md#cookie)).
- This works because browsers treat `http://localhost` as a **secure context**: Chrome and
  Firefox accept `Secure` cookies there.
- Dev URLs: `http://localhost:8030` (Vite, proxying to `app:80`) or `http://localhost:8080`
  (the app directly). See [frontend/development.md](../../frontend/development.md) and
  [docker/compose.md](../../docker/compose.md).

## Caveats

- Open dev through **`localhost`**, not a LAN IP or a custom hostname: those are not secure
  contexts over HTTP, so the browser drops the cookie and every request gets `401`.
- **Safari** may reject the `Secure` cookie over plain HTTP, even on `localhost`; use Chrome
  or Firefox in dev.
- The Vite dev proxy must forward the cookie-authenticated paths (`/auth/*.json`,
  `/admin/users/*.json` and Pidgey's admin endpoints; the `*.json` rule in
  [frontend/development.md](../../frontend/development.md#api-access)), and the origin check
  must accept the proxied dev origin. Implementing and checking this is a follow-up in #32.

## Production

Production must be served over **HTTPS**, either directly or behind a TLS-terminating proxy.
Behind a proxy, `TRUSTED_PROXY_HOPS` sets how many `X-Forwarded-For` hops are trusted for the
client IP (see [architecture/configuration.md](../../architecture/configuration.md)).

## Pending decisions

None.
