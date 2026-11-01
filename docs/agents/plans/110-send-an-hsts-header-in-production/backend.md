# Backend Plan: Send an HSTS header in production

Main plan: [plan.md](plan.md)

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

## Implementation Steps

### Step 1 — Add the HSTS header to `source/public/.htaccess`
Append the `<IfModule mod_headers.c>` block from the shared contract to the end of `source/public/.htaccess`. Keep the existing rewrite rules unchanged. Use `Header always set`, so redirects and error responses carry the header too. Use the `expr=%{HTTPS} == 'on'` condition so plain-HTTP responses never carry it. Add a short comment above the block: HTTPS only, staged rollout, `max-age` raised in #115, no `includeSubDomains` because other subdomains are not ours.

## Files to Change
- `source/public/.htaccess` — add the guarded HSTS header.

## CI Checks
- `source`: no PHP changes. The existing `backend_checks` and `backend_tests` jobs must stay green.

## Notes
- Behind FastCGI, `%{HTTPS}` may not be set the same way on the host. If the header does not show up in the post-release check, the fallback is `env=HTTPS` (the variable set by mod_ssl or the host). That has to be confirmed on the host, and it can't be tested locally.
- A security review (the `security` agent) should look at the final directive.
