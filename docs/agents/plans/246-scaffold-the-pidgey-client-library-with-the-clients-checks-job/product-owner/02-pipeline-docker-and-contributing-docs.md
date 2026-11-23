# Pipeline, docker and contributing docs

- `docs/agents/deployment/pipeline.md`:
  - a `clients_checks` row (both images, `clients/scripts/checks.sh`, partial Codacy upload on the current image only);
  - the `coverage_final` row requires `clients_checks` too;
  - in the coverage section, the clients' upload with prefix `clients/nodejs`.
- `docs/agents/docker/compose.md` (and `volumes.md` for `clients_nodejs_node_modules`): the `clients` service.
- `docs/agents/docker/makefile.md`: `make clients-checks`, and `make tests` now running it.
- `docs/agents/contributing.md`: in the check-jobs table, a row `clients/` → `clients_checks` → `make clients-checks`.
- `docs/agents/contributing/testing.md`: add the client suite and its commands, if that file lists suites.

## Files to Change
- `docs/agents/deployment/pipeline.md`: `clients_checks`, and `coverage_final`.
- `docs/agents/docker/compose.md`: the `clients` service.
- `docs/agents/docker/volumes.md`: the new volume.
- `docs/agents/docker/makefile.md`: the new target.
- `docs/agents/contributing.md`: the check-jobs row.
- `docs/agents/contributing/testing.md`: the client suite, if applicable.
