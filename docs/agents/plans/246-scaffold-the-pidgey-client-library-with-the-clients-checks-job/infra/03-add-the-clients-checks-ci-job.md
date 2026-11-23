# Add the clients_checks CI job

In `.circleci/config.yml`:

- Add the job `clients_checks`:
  - `parameters: node_image: { type: string }`;
  - `docker: - image: << parameters.node_image >>`.
  - Steps:
    1. `checkout`;
    2. `run: name: Client checks, command: clients/scripts/checks.sh`;
    3. a `when` step, `condition: equal: [ darthjee/circleci_node:0.2.1, << parameters.node_image >> ]`, containing `upload_codacy_coverage` with `report: clients/nodejs/coverage/lcov.info` and `prefix: clients/nodejs`.
  - Add a comment: floor = `engines` (22.12.0) on stock `cimg/node`; current = the CI Node image. Coverage is uploaded once.
- In `workflows.main.jobs`:
  - add `clients_checks` with `filters: *all_tags` and `matrix: parameters: node_image: [cimg/node:22.12.0, darthjee/circleci_node:0.2.1]`;
  - `coverage_final.requires` becomes `[backend_tests, frontend_checks, clients_checks]`;
  - leave the deploy jobs' `requires` unchanged.
- Keep the job free of any `context`.

## Files to Change
- `.circleci/config.yml`: the `clients_checks` job and matrix, and `coverage_final.requires`.
