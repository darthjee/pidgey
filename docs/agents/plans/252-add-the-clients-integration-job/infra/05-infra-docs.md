# Update the infra docs

- `docs/agents/deployment/pipeline.md`, "Check jobs" table: a `clients_integration` row (machine executor, `make clients-integration`, `requires: [clients_checks]`, no context, no coverage upload).
- `docs/agents/docker/compose.md`: the `integration_app` service (own database, `USER_APPROVAL_REQUIRED=false`, no host port, used only by `make clients-integration`).
- `docs/agents/docker/makefile.md`: the `clients-integration` target and `tests` now running it.

## Files to Change
- `docs/agents/deployment/pipeline.md` — new job row.
- `docs/agents/docker/compose.md` — new service.
- `docs/agents/docker/makefile.md` — new target, `tests` update.
