# Product-owner plan: Specs: production deployment

Main plan: [plan.md](plan.md)

## Overview
Turn the decisions of epic #95 and issue #96 into specs. The issue file is the checklist:
its tables "File layout", "Specs outside `deployment/`" and "Pending decisions" list every
file and every decision. This plan orders the work and adds the details to get right.

## Context
- Spec conventions (`docs/agents/specs.md`):
  - hub → `specs/<name>.md` index → `specs/<name>/*.md` details;
  - every file starts with a title and a `Purpose:` line and ends with **Pending decisions**;
  - specs describe *what* and *why*, not implementation.
- The deploy uses the shared `deploy_frontend.sh` from `darthjee/scripts`, currently `0.11.0`.
  Its actions and variables are documented in that image's README. `cleanup` is expected in
  `0.12.0`.
- **Never commit real host values.** No host names, SSH users, home paths or domains. Write
  them only as variable names (`SSH_REMOTE_DIR`, `REMOTE_HOME`, …) or obvious placeholders
  (`<shared folder>`).

## Steps

- [01 — Deployment folder](product-owner/01-deployment-folder.md)
- [02 — Specs outside deployment/](product-owner/02-specs-outside-deployment.md)
- [03 — Index, inbound links and consistency pass](product-owner/03-index-links-and-consistency.md)

## CI Checks
- No CI job checks docs content (`.circleci/config.yml` runs backend and frontend checks
  only). Check by hand that every relative link resolves:

  ```bash
  grep -rn "deployment" docs/agents --include=*.md
  ```

  Then open each linked path that changed.

## Notes
- `docs/agents/contributing.md` is outside the product-owner's scope. Its jobs table must be
  updated to the real deploy job names, still marked *(planned)* until #99 lands. The
  architect applies that edit while reviewing this work (step 03 lists the exact change).
- A `security` agent review is required before merge. It covers `deployment/security.md`,
  `deployment/server.md`, `deployment/release.md`, and the env-file loader and
  `check-config` parts of `architecture/configuration.md`.
- Settle only the pending decisions listed as settled in the issue. Keep the others as
  explicit **Pending decision** items.
