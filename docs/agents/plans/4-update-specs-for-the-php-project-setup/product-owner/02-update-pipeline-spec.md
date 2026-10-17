# Update the CI/CD pipeline spec
Describe the CI that sub-issue #8 builds, keeping the rest as future work.

- State that every job runs in `circleci_pidgey-base` (not `dev_pidgey`).
- Replace the `backend_checks` row with two jobs:
  - `backend_checks`: `composer lint` (phpcs + slevomat, phpmd).
  - `backend_tests`: MySQL and Mailpit service containers; wait-for-db; create the test
    database; run migrations; `composer coverage`; upload the Clover report to Codacy with a
    plain `report` command using `CODACY_PROJECT_TOKEN` (set only in CircleCI).
- Mark `frontend_checks`, `build_images`, `upload_fe_files` and `release` as future work;
  note that images are built and pushed manually for now.
- Keep the "Pending decisions" paragraph unchanged (out of scope).

## Files to Change
- `docs/agents/specs/deployment/pipeline.md` — CI image, job split, Codacy, future-work marks.
