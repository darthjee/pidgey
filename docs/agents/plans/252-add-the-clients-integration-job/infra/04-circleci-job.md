# Add the clients_integration CircleCI job

New job `clients_integration`:

- `machine: image: ubuntu-2404:<pinned current release>` (machine executor: the stack is booted with `docker compose`, see the rejected executors in `docs/agents/specs/node-client/testing.md`).
- Steps: `checkout`; build the dev image (`docker compose build base_build`); `make clients-integration` — the same commands as locally. The `clients` service image (`darthjee/node:0.2.1`) is the current Node.js version, so there is no matrix.
- No `context`, no publishing or deploy credential.

Workflow `main`: add `clients_integration` with `requires: [clients_checks]` and `filters: *all_tags`. It does not feed `coverage_final` (no coverage upload) and is not required by the deploy jobs.

## Files to Change
- `.circleci/config.yml` — new job and workflow entry.
