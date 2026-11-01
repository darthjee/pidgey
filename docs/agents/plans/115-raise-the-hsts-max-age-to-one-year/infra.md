# Infra Plan: Raise the HSTS max-age to one day

Main plan: [plan.md](plan.md)

## Overview
Second step of the HSTS rollout started in #110: change the header's `max-age` from `300` to `86400` (60*60*24). Nothing else about the header changes: it stays HTTPS-only, with no `includeSubDomains` and no `preload`.

## Context
- #110 is merged, deployed and verified on the host with `max-age=300`.
- The header is set in `source/public/.htaccess` under `mod_headers`, guarded by `expr=%{HTTPS} == 'on'`, so local docker and CI (plain HTTP) never send it. `public/admin/` inherits it.
- One day caps any lockout after an HTTPS failure (an expired cert, a host migration) at 24 hours. One year is a later, separate step.
- No test asserts the header value; the only references to `max-age=300` are the four files below.

## Implementation Steps

### Step 1 — Raise the header value
In `source/public/.htaccess`, change `max-age=300` to `max-age=86400`. Rewrite the comment above it: the rollout is staged and now at one day (#115), with one year as a later step; keep the "no includeSubDomains: other subdomains of the parent domain are not ours" note.

### Step 2 — Update the docs and spec
- `docs/agents/specs/deployment/security.md` (HTTPS section): the decision states `max-age=86400` (#110, raised in #115). Replace the "`max-age=300` is the first step..." bullet with one explaining one day: it caps any lockout after an HTTPS failure at 24 hours, and one year comes in a later step.
- `docs/agents/deployment/operations.md` (release verification): expect `Strict-Transport-Security: max-age=86400`.
- `docs/agents/deployment/server.md`: state `max-age=86400`.

## Files to Change
- `source/public/.htaccess` — header value and comment.
- `docs/agents/specs/deployment/security.md` — HSTS decision and rollout bullet.
- `docs/agents/deployment/operations.md` — expected header in release verification.
- `docs/agents/deployment/server.md` — header value.

## Notes
- After this is deployed, re-run `curl -sI https://<domain>/health.json` and `curl -sI https://<domain>/admin/` and check they show `max-age=86400`.
- Check with `grep -rn "max-age=300" --exclude-dir=node_modules --exclude-dir=vendor .` that no stale reference remains outside `docs/agents/issues/` and `docs/agents/plans/`.
