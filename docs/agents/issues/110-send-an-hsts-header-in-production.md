# Issue: Send an HSTS header in production

## Description
The production host already redirects HTTP to HTTPS, but no `Strict-Transport-Security` header is sent. This is recorded as a pending decision in `docs/agents/specs/deployment/security.md` and as a known gap in `docs/agents/deployment/known-gaps.md`. Raised while refining #101.

## Problem
Without HSTS, a browser's first plain-HTTP request can still be intercepted before the redirect, which is a downgrade or SSL-stripping risk.

## Expected Behavior
- Every HTTPS response carries `Strict-Transport-Security: max-age=300`. This covers the API and the admin UI under `/admin/`, which is deployed into `public/admin/` and so inherits `source/public/.htaccess`.
- The header is only sent over HTTPS. It is never sent in local docker or in CI. Local docker serves plain HTTP and does not enable `mod_headers` (only `rewrite` is enabled in `dockerfiles/dev_pidgey-base/Dockerfile`).
- `includeSubDomains` and `preload` are not sent.

## Solution
- Add the header in `source/public/.htaccess`, not in PHP, so static admin files get it too. Guard it with `<IfModule mod_headers.c>` (the same pattern as `frontend/public/.htaccess`) and limit it to HTTPS requests: for example `Header always set Strict-Transport-Security "max-age=..." env=HTTPS`, or an `<If "%{HTTPS} == 'on'">` block. Check the exact form on the host, because behind FastCGI `HTTPS` may only be visible as a variable.
- Ship `max-age=300` only. This is the first step of a staged rollout. Raising it to one year after the host check is tracked in #115.
- Verification is manual, on the host, after the release: `curl -sI https://<domain>/health.json` and `curl -sI https://<domain>/admin/` show the header. There is no CI check: local docker has no `mod_headers` and no HTTPS, so it would prove little.
- Leave out `includeSubDomains` and `preload` for now. They would affect other subdomains on the same domain, which this app doesn't own.
- Docs: record the decision in `docs/agents/specs/deployment/security.md` (replace the HSTS pending decision), remove the "No HSTS header" entry from `docs/agents/deployment/known-gaps.md`, and add the `curl -sI` check to the release verification in `docs/agents/deployment/operations.md`.
- Owner: infra, with a security review.

## Benefits
- Closes the first-request downgrade window for returning browsers.
- Resolves an open pending decision and known gap in the deployment docs.
