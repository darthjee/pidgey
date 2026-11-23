# Product-owner Plan: Scaffold the pidgey-client library with the clients_checks job

Main plan: [plan.md](plan.md)

## Shared contracts

- **Rely on:**
  - nodejs:
    - the package `pidgey-client` `0.1.0` in `clients/nodejs/` (`lib/`, `specs/`, `pack-files.txt`);
    - the scripts `lint`, `typecheck`, `test` and `coverage` (100 %);
    - stub `PidgeyClient` and `PidgeyError` (with `name` and `code`), with no `send`.
  - infra:
    - `clients/scripts/checks.sh`;
    - the compose `clients` service and `make clients-checks` (which `make tests` calls);
    - the `clients_checks` matrix on `cimg/node:22.12.0` and `darthjee/circleci_node:0.2.1`, with the Codacy upload on the latter and `coverage_final` requiring it.
- **Commands to document:**
  - `make clients-checks`;
  - `docker compose run --rm clients clients/scripts/checks.sh`;
  - `docker compose run --rm clients sh -c "cd clients/nodejs && yarn <lint|typecheck|test|coverage>"`.

## Steps

- [01 — Client as-built docs and indexes](product-owner/01-client-as-built-docs-and-indexes.md)
- [02 — Pipeline, docker and contributing docs](product-owner/02-pipeline-docker-and-contributing-docs.md)
- [03 — Hand-off from #243](product-owner/03-hand-off-from-243.md)

## Notes

- Document only what is built. `send`, retries, types, the CLI and the smoke test stay in the specs and are marked as coming in #247–#250.
- Step 03 edits files owned by `architect`. Keep the changes limited to the hand-off, so the architect can review them as part of the PR.
