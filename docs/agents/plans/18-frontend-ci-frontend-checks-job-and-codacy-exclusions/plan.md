# Plan: Frontend CI: frontend_checks job and Codacy exclusions

Issue: [18-frontend-ci-frontend-checks-job-and-codacy-exclusions.md](../../issues/18-frontend-ci-frontend-checks-job-and-codacy-exclusions.md)

## Overview
Add a `frontend_checks` CircleCI job (frozen-lockfile `yarn install`, `npm run lint`, `npm run coverage` on `darthjee/circleci_node:0.2.1`) to the `main` workflow, add the frontend exclusions to `.codacy.yml`, and align `deployment/pipeline.md` with `--frozen-lockfile`. All work is in infra's scope.

See [infra.md](infra.md) for the full plan.
