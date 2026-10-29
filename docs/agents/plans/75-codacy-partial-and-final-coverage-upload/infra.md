# Infra Plan: Codacy partial and final coverage upload

Main plan: [plan.md](plan.md)

## Shared contracts

- Rely on: `coverage/clover.xml` after `composer coverage` in `backend_tests`, and `coverage/lcov.info` after `npm run coverage` in `frontend_checks` (both relative to the flattened project root).
- Produce: partial Codacy uploads with repo-relative paths (`source/...`, `frontend/...`), plus a `final` call once both jobs succeed.

## Steps

- [01 — Add the upload_codacy_coverage command](infra/01-upload-command.md)
- [02 — Use it in backend_tests and frontend_checks](infra/02-wire-test-jobs.md)
- [03 — Add the coverage_final job](infra/03-coverage-final-job.md)

## CI Checks
- `.circleci/config.yml`: `circleci config validate` if the CLI is available, otherwise a YAML parse (e.g. `ruby -ryaml -e 'YAML.load_file(".circleci/config.yml")'`). Jobs: `backend_tests`, `frontend_checks`, `coverage_final`.

## Notes
- `CODACY_PROJECT_TOKEN` comes from the CircleCI project settings. Don't add it to the YAML.
- `backend_tests` already fails on main, so `coverage_final` won't run until that's fixed. Per the CI policy this PR may merge with CircleCI red. Don't change `.circleci/config.yml` to hide failures, and don't add `when: always` / `|| true` to make the upload or finalize pass.
- Don't change `backend_checks` or the test commands themselves.
