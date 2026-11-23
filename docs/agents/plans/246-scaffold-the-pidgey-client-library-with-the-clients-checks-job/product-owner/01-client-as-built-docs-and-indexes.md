# Client as-built docs and indexes

- `docs/agents/clients.md` (new hub):
  - what `clients/` holds: `nodejs/` now, `cli/` coming in #249, and `scripts/`;
  - the owners;
  - the shared `checks.sh`;
  - links to `clients/nodejs.md` and to the specs under `docs/agents/specs/node-client/`.
- `docs/agents/clients/nodejs.md` (new):
  - the package scaffold: name, version `0.1.0`, ESM, `exports`, `engines`, `files`, zero runtime dependencies, no install scripts;
  - the current stub surface;
  - the scripts and the 100 % threshold;
  - the pack file-list check and how to regenerate `pack-files.txt`;
  - the local commands, and the CI job.
- `AGENTS.md`: the `Clients` row links `docs/agents/clients.md`.
- `docs/agents/specs.md`: the `clients` row links the as-built `docs/agents/clients.md`, next to the spec.

## Files to Change
- `docs/agents/clients.md`: new.
- `docs/agents/clients/nodejs.md`: new.
- `AGENTS.md`: the `Clients` row.
- `docs/agents/specs.md`: the `clients` row.
