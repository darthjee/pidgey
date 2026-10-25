# Infra Plan: Frontend CI: frontend_checks job and Codacy exclusions

Main plan: [plan.md](plan.md)

## Overview
Run frontend lint and tests on CircleCI and keep frontend artifacts out of Codacy static analysis, following citadel's `yarn_project` job without its `upload_fe_files`/`release` jobs.

## Context
- `.circleci/config.yml` has only `backend_checks` and `backend_tests`, both with `filters: *all_tags` in the `main` workflow.
- `frontend/package.json` already defines `lint` (`eslint assets specs`) and `coverage` (c8 + Jasmine, lcov → `coverage/lcov.info`); `frontend/yarn.lock` is committed.
- `.codacy.yml` `exclude_paths` lists only backend/tooling paths.
- `docs/agents/specs/deployment/pipeline.md` already describes `frontend_checks` and the Codacy exclusions; only the install command wording changes.
- Decided in discussion: `--frozen-lockfile`; keep `npm run`; no `node_modules` cache; no extra Codacy exclusions (`frontend/dist/`, `frontend/.nyc_output/`, `source/public/admin/` are git-ignored); no Codacy frontend coverage upload.

## Implementation Steps

### Step 1 — Add the `frontend_checks` job
In `.circleci/config.yml`, add under `jobs:` (after `backend_tests`):

```yaml
  frontend_checks:
    docker:
      - image: darthjee/circleci_node:0.2.1
    steps:
      - checkout
      - run:
          name: Set folder
          command: mv frontend /tmp/frontend; rm -rf ./*; cp -R /tmp/frontend/. ./
      - run:
          name: Yarn install
          command: yarn install --frozen-lockfile
      - run:
          name: Lint
          command: npm run lint
      - run:
          name: Tests
          command: npm run coverage
```

and add `- frontend_checks: { filters: *all_tags }` (block style, matching the existing entries) to `workflows.main.jobs`. No cache steps, no Codacy upload.

### Step 2 — Codacy exclusions and spec wording
- `.codacy.yml`: append `"frontend/coverage/**"`, `"frontend/node_modules/**"`, `"**/yarn.lock"` to `exclude_paths`, quoted like the existing entries.
- `docs/agents/specs/deployment/pipeline.md`: in the `frontend_checks` table row, change `yarn install` to `yarn install --frozen-lockfile`. Nothing else in the spec changes.

## Files to Change
- `.circleci/config.yml` — new `frontend_checks` job and workflow entry.
- `.codacy.yml` — three new `exclude_paths` entries.
- `docs/agents/specs/deployment/pipeline.md` — `--frozen-lockfile` in the `frontend_checks` row.

## CI Checks
- `frontend`: `make tests` (frontend half: `yarn lint` + `yarn coverage` in `frontend_dev`) (CI job: `frontend_checks`)
- Optional local reproduction of the CI install: `docker run --rm -v "$PWD/frontend:/src:ro" darthjee/circleci_node:0.2.1 sh -c 'cp -R /src/. ~/app && cd ~/app && yarn install --frozen-lockfile && npm run lint && npm run coverage'`

## Notes
- If `yarn.lock` is out of sync with `package.json`, `--frozen-lockfile` fails the job; fix by regenerating the lockfile (`make fe-install`) and committing it, not by dropping the flag.
- `rm -rf ./*` leaves dotfiles at the root (e.g. `.circleci`, `.codacy.yml`); harmless, same as citadel.
- Validate the YAML (e.g. `circleci config validate` if available) before pushing.
