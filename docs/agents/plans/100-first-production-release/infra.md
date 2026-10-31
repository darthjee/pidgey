# Infra Plan: First production release

Main plan: [plan.md](plan.md)

## Overview
Everything in this issue happened outside the repository: the hosting panel, the server, CircleCI context settings and SSH keys. All of it is done and verified on the live release `0.1.0`. This plan has no file changes. Merging the branch only adds the issue file and this plan, and closes #100.

## Context
- The deploy pipeline (#99), the env-file loader and `bin/check-config` (#97) and the CI images (#98) were already merged.
- During the discussion of this issue, the owner did all of this, and the architect checked it on the host or over HTTPS:
  - created the deploy key, `SSH_KNOWN_HOSTS` and the `pidgey-deploy` context;
  - created the shared `.env`;
  - pointed the web directory at `public/`;
  - checked PHP, MySQL and the grants;
  - pushed tag `0.1.0`.
- The release was verified:
  - the API answers over HTTPS;
  - `/admin/` loads;
  - all 8 migrations are applied;
  - nothing outside `public/` is served;
  - no staging or `_old` folders are left.
- The owner decided not to add the GitHub tag ruleset, since they are the only collaborator.

## Implementation Steps

### Step 1 — No changes
Do not change any file. Real host names, users, paths and domains must never be committed (`docs/agents/specs/deployment/server.md`), so none of the values used during setup belong in the repository. The "To confirm" list in `server.md` is left as is: the deployment specs are removed in #102, and the operational findings go to the implementation docs in #101.

## Files to Change
- None.

## Notes
- The issue file lists the findings for #101: the escaped one-line `SSH_PRIVATE_KEY`, the single-line ED25519 `SSH_KNOWN_HOSTS`, the missing `cleanup` in version `0.11.0` of the deploy script, unknown routes returning 200 because the status handler is the only handler, and `TRUSTED_PROXY_HOPS=0` not yet being checkable from outside.
- Making unknown routes return 404 is out of scope here and belongs in its own issue.
