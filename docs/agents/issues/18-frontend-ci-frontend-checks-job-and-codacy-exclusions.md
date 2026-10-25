# Issue: Frontend CI: frontend_checks job and Codacy exclusions

## Description

Part of #15. Run the frontend lint and tests on CircleCI and keep frontend artifacts out of Codacy static analysis. The job is modelled on the `yarn_project` job / `setup_project` command in citadel's `.circleci/config.yml`, without its `upload_fe_files` and `release` jobs. The target behaviour is already specified in `docs/agents/specs/deployment/pipeline.md` and `docs/agents/specs/frontend/testing.md`.

## Problem

`.circleci/config.yml` only has `backend_checks` and `backend_tests`, so frontend lint errors and failing Jasmine specs are never caught on a PR. `.codacy.yml` excludes only backend and tooling paths, so frontend artifacts such as `node_modules`, coverage reports and `yarn.lock` could be analysed if they ever reach the repository.

## Expected Behavior

- A `frontend_checks` CircleCI job runs on every branch and every tag, in parallel with `backend_checks` and `backend_tests`.
- It installs dependencies with a frozen lockfile, so a `yarn.lock` that is out of sync with `package.json` fails the build.
- It fails when `npm run lint` or `npm run coverage` fails.
- Codacy ignores `frontend/coverage/**`, `frontend/node_modules/**` and `**/yarn.lock`.

## Solution

- **`.circleci/config.yml`:** add the job
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
  and add it to the `main` workflow with `filters: *all_tags`.
  - No `node_modules` cache, same as citadel and the backend jobs.
  - The checks keep using `npm run`, as citadel and the spec do.
- **`.codacy.yml` `exclude_paths`:** add `frontend/coverage/**`, `frontend/node_modules/**` and `**/yarn.lock`.
  - Nothing else is needed: `frontend/dist/`, `frontend/.nyc_output/` and `source/public/admin/` are git-ignored, so Codacy never sees them.
- **Spec:** in `docs/agents/specs/deployment/pipeline.md`, change the `frontend_checks` row to say `yarn install --frozen-lockfile`.

### Out of scope

- Uploading frontend coverage to Codacy (switching the backend upload to `report --partial` plus a final step). This is still a pending decision in `pipeline.md`.
- `upload_fe_files` and release jobs.

### Testing

`frontend_checks` passes on CircleCI for the PR, alongside the existing backend jobs.

## Benefits

- Frontend regressions and lint violations are caught on every PR, not only through local `make tests`.
- Lockfile drift is caught early.
- Codacy results stay free of generated or vendored frontend noise.
