# Product Owner Plan: Update specs for the PHP project setup

Main plan: [plan.md](plan.md)

## Overview
Rewrite the affected specs so sub-issues #5–#8 (Docker dev environment, lint/tests, DB
infrastructure, CircleCI) can follow them exactly. Specs are **kept, not removed**: anything
not delivered by Epic #3 (frontend, production image, release, `build_images`) stays in the
specs, marked as future work. No code, Dockerfile or CI file is created.

## Context
- Decisions come from Epic #3 and issue #4: PHP 8.4; `dev_pidgey-base` with `zip`, `pdo`,
  `pdo_mysql`, `pcov`, Composer, `mod_rewrite`, non-root `app` user (uid 1000), no `gd`,
  Node.js/Yarn deferred; multi-arch tags via `BASE_SUFFIX`; new CI image
  `circleci_pidgey-base` (`cimg/php:8.4`, vendor at `/home/circleci/vendor`); images built and
  pushed manually to Docker Hub `darthjee/`; CI split into `backend_checks` and
  `backend_tests`; web root `source/public/`.
- Spec granularity rule (`docs/agents/specs.md`): hub → index → detail. No new spec files are
  needed; only existing detail files and their index summaries change.
- `AGENTS.md` rule 3: resolved items must stop being marked "Pending decision"; unresolved
  ones (pipeline hosting/`dist/` upload, image tagging, migrations on deploy; the Node.js
  location) stay pending.

## Steps

- [01 — Update Docker specs](product-owner/01-update-docker-specs.md)
- [02 — Update the CI/CD pipeline spec](product-owner/02-update-pipeline-spec.md)
- [03 — Update architecture and testing specs](product-owner/03-update-architecture-and-testing-specs.md)
- [04 — Align agent docs and verify](product-owner/04-align-agent-docs-and-verify.md)

## Notes
- Intentionally left as is (decided during enhancement): the CI table in
  `docs/agents/contributing.md` (lines ~76–78) and the Node.js references in
  `docker/compose.md` (`tests` running frontend tests, `frontend_dev` on `dev_pidgey`).
  Only the "8.x" mention in `contributing.md` changes.
- No `.circleci/` config exists yet, so there are no CI checks for this change; verification is
  the grep checks in step 04.
- Keep the specs' existing table style and line width (~95 columns).
