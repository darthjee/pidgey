# Infra Plan: Send an HSTS header in production

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

### Step 1 — Update the deployment docs
- `docs/agents/deployment/known-gaps.md`: remove the "## No HSTS header" section.
- `docs/agents/deployment/server.md` (`## Web directory`): add a bullet saying `public/.htaccess` sends `Strict-Transport-Security: max-age=300` over HTTPS only, inherited by `public/admin/`. Link the spec decision in `../specs/deployment/security.md`.
- `docs/agents/deployment/operations.md` (`## Release`, step 4 "Verify the release"): add a check that `curl -sI https://<domain>/health.json` and `curl -sI https://<domain>/admin/` both show `Strict-Transport-Security: max-age=300`.

## Files to Change
- `docs/agents/deployment/known-gaps.md` — remove the HSTS gap.
- `docs/agents/deployment/server.md` — describe the header.
- `docs/agents/deployment/operations.md` — add the header check to the release verification.

## Notes
- No Docker, Makefile or CircleCI changes. Local docker keeps `mod_headers` disabled and serves plain HTTP, which is fine because the header is production-only.
