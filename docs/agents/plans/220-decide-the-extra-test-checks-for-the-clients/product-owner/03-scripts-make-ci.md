# Scripts, Makefile targets and CI jobs

Rewrite `testing.md` "CI and `make tests`" from the issue's "Commands, `Makefile` targets and CI jobs" section.

- One script per job, shared by the `Makefile` and CI: `clients/scripts/checks.sh` (per package `yarn install --frozen-lockfile`, `yarn lint`, `yarn typecheck`, `yarn coverage`, then the pack file-list check and the smoke test with the `.d.ts` check) and `clients/scripts/integration.sh` (the integration spec with the "required" flag). Paths are indicative.
- `make clients-checks` runs `checks.sh` in a compose Node service; `make clients-integration` boots the compose stack and runs `integration.sh`; `make tests` runs backend + frontend + `clients-checks` + `clients-integration` (accepted: slower locally). Note the matrix exception (floor version in CI only).
- Jobs table: `clients_checks` (matrix, `checks.sh`, parallel with `backend_checks`, `backend_tests`, `frontend_checks`, Codacy upload per package with prefixes `clients/nodejs` and `clients/cli` on the current version); `clients_integration` (machine executor, `requires: [clients_checks]`, current version only); `coverage_final` also requires `clients_checks`.
- Keep the existing rules (the check job ships with the scaffold; `make tests` and CI call the same commands; the "Done means" line), adjusted to the scripts.

## Files to Change
- `docs/agents/specs/node-client/testing.md` — "CI and `make tests`".
