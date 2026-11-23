# CI job and compose volume

- `.circleci/config.yml`, job `clients_checks`:
  - after `Client checks`, a step `Working tree unchanged` running
    `test -z "$(git status --porcelain)" || { git status --porcelain; exit 1; }`, which proves
    the packs left the checkout untouched (`node_modules/` and `coverage/` are gitignored);
  - in the `when` block on `darthjee/circleci_node:0.2.1`, a second
    `upload_codacy_coverage` with `report: clients/cli/coverage/lcov.info` and
    `prefix: clients/cli`, after the `clients/nodejs` one.
  - Matrix, filters and `coverage_final` stay as they are.
- `docker-compose.yml`, service `clients`: add the volume
  `./docker_volumes/clients_cli_node_modules:/home/node/app/clients/cli/node_modules`, next to
  the `nodejs` one. `make clients-checks` and `make tests` need no change.

## Files to Change

- `.circleci/config.yml` — clean-tree step and CLI coverage upload in `clients_checks`.
- `docker-compose.yml` — CLI `node_modules` volume on `clients`.
