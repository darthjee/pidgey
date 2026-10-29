# Issue: Codacy partial and final coverage upload

## Description

Part of #72 (Bootstrap a testable request pipeline and CI coverage).

Report both backend and frontend coverage to Codacy. Today `backend_tests` uploads `coverage/clover.xml` as a full report on its own, and the frontend lcov coverage (`npm run coverage` in `frontend_checks`) is never uploaded. Backend and frontend tests already run in parallel and must keep doing so.

## Problem

- Codacy only ever sees backend coverage, sent as a complete report, so frontend coverage is missing.
- Both CI jobs flatten their folder (`source/` or `frontend/`) to the project root before running, so the paths in the generated reports lack the `source/` / `frontend/` prefix Codacy needs to map them to repository files.
- `frontend/package.json`'s `c8` config has no `all`/`include`, so frontend files that no spec loads are left out of the frontend coverage numbers.

## Expected Behavior

- `backend_tests` and `frontend_checks` each upload a **partial** Codacy report, with paths rewritten to be repo-relative (`source/...`, `frontend/...`).
- A new `coverage_final` job runs after both and finalizes the commit's coverage in Codacy.
- Frontend coverage counts every file under `frontend/assets/`, including files no spec covers.
- If either test job fails or is skipped, `coverage_final` does not run and Codacy does not finalize coverage for that commit. This is accepted.

## Solution

Use Codacy partial reports plus a finalize job, the pattern Tent and Oak already use. All changes go in `.circleci/config.yml` and `frontend/package.json`.

1. **Reusable command** `upload_codacy_coverage`, with params `report` (path to the report) and `prefix` (`source` or `frontend`). It:
   - rewrites the report's file paths to be repo-relative, every time (not "only if Codacy fails to map"): the absolute `$(pwd)/` prefix (and a leading `./` in lcov `SF:` lines, if present) becomes `<prefix>/`. This covers Clover `<file name="...">` / `<package>` paths and lcov `SF:` lines;
   - uploads with `bash <(curl -Ls https://coverage.codacy.com/get.sh) report --partial -r <report>`.
2. **Backend** (`backend_tests`): keep `composer coverage` (PHPUnit + pcov → `coverage/clover.xml`). Replace the current full upload with `upload_codacy_coverage` (`report: coverage/clover.xml`, `prefix: source`).
3. **Frontend** (`frontend_checks`): after `npm run coverage` (c8 → `coverage/lcov.info`), run `upload_codacy_coverage` (`report: coverage/lcov.info`, `prefix: frontend`).
4. **c8 scope**: add `"all": true` and `"include": ["assets/**"]` to the `c8` block in `frontend/package.json`. The existing `.js`/`.jsx` extensions stay.
5. **Finalize**: new `coverage_final` job on `darthjee/circleci_node:0.2.1`, with no checkout, running `bash <(curl -Ls https://coverage.codacy.com/get.sh) final`. In the workflow it has `requires: [backend_tests, frontend_checks]` and the same `*all_tags` filters.

### Notes

- `CODACY_PROJECT_TOKEN` is set in the CircleCI project settings, not in the YAML.
- `backend_tests` currently fails on main (tracked separately). Until it passes, `coverage_final` won't run. Per the CI policy this PR may merge with CircleCI red, and nothing in `.circleci/config.yml` may be changed to hide failures.

### Out of scope

- Changing which tests run or adding new test suites.
- Fixing the existing backend CI failures.

### Testing

Verified on CircleCI:
- the rewritten reports contain `source/...` / `frontend/...` paths;
- the frontend partial upload succeeds, and so does the backend one once `backend_tests` passes;
- `coverage_final` runs after both;
- Codacy shows combined coverage for the commit, with files mapped to the repository.

### Responsible agent

infra

## Benefits

- Codacy reports combined backend and frontend coverage for every commit.
- Deterministic path mapping, with no trial and error.
- Honest frontend numbers, because untested files count.
