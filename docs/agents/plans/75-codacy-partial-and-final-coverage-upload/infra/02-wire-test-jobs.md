# Use it in backend_tests and frontend_checks
- `backend_tests`: replace the existing "Upload coverage to Codacy" step (a full `report -r coverage/clover.xml`) with `upload_codacy_coverage` (`report: coverage/clover.xml`, `prefix: source`), still right after `composer coverage`.
- `frontend_checks`: after the `npm run coverage` step, add `upload_codacy_coverage` (`report: coverage/lcov.info`, `prefix: frontend`).

Both jobs keep running in parallel. Neither gets a new `requires`.

## Files to Change
- `.circleci/config.yml` — `backend_tests` and `frontend_checks` steps.
