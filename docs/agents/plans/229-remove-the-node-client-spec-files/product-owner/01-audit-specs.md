# Audit the specs against the as-built docs

Go through every section of `docs/agents/specs/node-client.md` and of each file in `docs/agents/specs/node-client/`. Find where each rule now lives in the as-built docs:

- `docs/agents/clients.md`, `docs/agents/clients/nodejs.md` and `docs/agents/clients/cli.md`;
- `docs/agents/api/`, for `GET /v1/openapi.json` and the additive-only rule with its CI diff;
- `docs/agents/deployment/` and `docs/agents/contributing.md`, for `clients_checks`, `clients_integration`, publishing, `Makefile` targets and versioning.

Some rules will be found only in the specs. Move each one into the matching as-built doc, written as current behaviour rather than as a plan. Moves into `deployment/` or `contributing.md` are `architect`'s to make.

Skip what only makes sense before implementation, such as the spec's Lifecycle and Pending decisions sections and the "spec, not built yet" framing.

Record the mapping (spec file and section, then the as-built doc and section) for the PR description.

## Files to Change
- `docs/agents/clients.md`, `docs/agents/clients/nodejs.md`, `docs/agents/clients/cli.md`: add only the rules found missing.
- `docs/agents/api/**`: add only the rules found missing.
- `docs/agents/deployment/**`, `docs/agents/contributing.md`: add only the rules found missing (`architect`).
