# Product-owner Plan: Send an HSTS header in production

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

### Step 1 — Record the HSTS decision in the deployment security spec
In `docs/agents/specs/deployment/security.md`:
- Remove the "**Pending decision — HSTS**" bullet from `## Pending decisions`.
- Under `## HTTPS`, state the decision: `source/public/.htaccess` sends `Strict-Transport-Security: max-age=300` on HTTPS responses only (API and `/admin/`). There is no `includeSubDomains` and no `preload`, because other subdomains on the domain are not owned by Pidgey. The value is the first step of a staged rollout, and #115 raises it to one year after the host check.

## Files to Change
- `docs/agents/specs/deployment/security.md` — replace the pending decision with the recorded HSTS decision.
