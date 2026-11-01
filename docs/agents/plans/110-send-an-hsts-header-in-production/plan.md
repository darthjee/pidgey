# Plan: Send an HSTS header in production

Issue: [110-send-an-hsts-header-in-production.md](../../issues/110-send-an-hsts-header-in-production.md)

## Overview
Send `Strict-Transport-Security: max-age=300` from `source/public/.htaccess`, only over HTTPS, so the API and the admin UI both get it. Record the decision in the deployment security spec, and update the deployment docs: remove the known gap, describe the header, and add a `curl -sI` check to the release verification. Raising `max-age` to one year is out of scope (#115).

## Agents involved

- [backend](backend.md)
- [product-owner](product-owner.md)
- [infra](infra.md)

## Shared contracts

- Header: `Strict-Transport-Security: max-age=300`. No `includeSubDomains`, no `preload`.
- Where: `source/public/.htaccess` (deployed as `<live>/public/.htaccess`). It covers the API and `/admin/`, because `public/admin/` inherits it.
- Directive (default form):

  ```apache
  <IfModule mod_headers.c>
    Header always set Strict-Transport-Security "max-age=300" "expr=%{HTTPS} == 'on'"
  </IfModule>
  ```

  It is sent only over HTTPS. Local docker does not enable `mod_headers` and serves plain HTTP, so the header never appears there or in CI.
- Verification (manual, after a release): `curl -sI https://<domain>/health.json` and `curl -sI https://<domain>/admin/` both show the header.
- Follow-up: raising `max-age` to one year is #115.
