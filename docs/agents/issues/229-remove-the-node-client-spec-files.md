# Issue: Remove the Node client spec files

## Description

- Parent epic: #219. This is its last sub-issue: it is blocked until every other sub-issue of #219 is closed (#244 to #254, the last being #254, the `0.1.0` release).
- Owners: `product-owner` (the docs under `docs/agents/`) and `architect` (the agent files under `.claude/agents/`).
- The specs themselves plan this step: the Lifecycle section of `docs/agents/specs/node-client.md` says #229 deletes the spec hub and `docs/agents/specs/node-client/` once the as-built docs cover every rule. No **Pending decision** is open in the specs.

## Problem

Epic #219 wrote its specs first, in `docs/agents/specs/node-client.md` and the six files of `docs/agents/specs/node-client/`:

- `library.md`;
- `errors-and-retry.md`;
- `cli.md`;
- `openapi.md`;
- `packaging.md`;
- `testing.md`.

It then implements the library `pidgey-client` and the CLI `pidgey-cli`. Each implementation sub-issue writes the as-built docs under `docs/agents/clients/`, with the hub `docs/agents/clients.md`.

Once everything is built, keeping the specs would leave two descriptions of the same packages, and they would drift apart. Two places still point readers at the specs:

- the `node-client` row of the `## Specs` table in the docs hub `docs/agents/specs.md`;
- the reading-order line of `.claude/agents/nodejs.md` and of `.claude/agents/cli.md`.

## Expected Behavior

- `docs/agents/specs/node-client.md` and `docs/agents/specs/node-client/` no longer exist.
- No file outside `docs/agents/issues/` and `docs/agents/plans/` links to them. Those two folders are historical records and are left untouched.
- `docs/agents/specs.md` still exists and indexes the `clients` area.
  - Its `## Specs` section and the intro sentence about specs stay.
  - The section's table is replaced by the single line "No spec in progress." A future spec adds its table back.
- `.claude/agents/nodejs.md` and `.claude/agents/cli.md` point only at their as-built doc, `docs/agents/clients/nodejs.md` and `docs/agents/clients/cli.md`. The "while they exist" wording and the "(planned: …)" notes are gone.
- No information from the specs is lost: every rule is in the as-built docs.

### Testing

Docs-only change, with no code and no test. The check is a search: `grep -rn "specs/node-client"` over the repo, excluding `docs/agents/issues/` and `docs/agents/plans/`, returns nothing.

## Solution

1. **Audit** (`product-owner`). Check every section of the spec hub and of the six spec files against the as-built docs:
   - `docs/agents/clients.md`, `docs/agents/clients/nodejs.md` and `docs/agents/clients/cli.md`;
   - `docs/agents/api/` for the OpenAPI route and the additive-only rule;
   - `docs/agents/deployment/` and `docs/agents/contributing.md` for publishing, CI jobs and `Makefile` targets.

   First move any rule found only in the specs into the matching as-built doc. List the mapping (spec section to as-built doc) in the PR description, not in a committed file.
2. **Delete** `docs/agents/specs/node-client.md` and `docs/agents/specs/node-client/` (`product-owner`).
3. **Hub** `docs/agents/specs.md` (`product-owner`). Remove the `node-client` row, and replace the emptied table with "No spec in progress." Keep the `## Specs` heading, its intro text, and the hub's intro sentence about specs.
4. **Agent files** (`architect`). In `.claude/agents/nodejs.md` and `.claude/agents/cli.md`, rewrite the reading-order line so it points only at the as-built doc. Drop "while they exist" and the "(planned: #246)" and "(planned: #249)" notes. Keep the rest of the line: `product-owner` updates the docs in the same change set as the code, and **Pending decisions** are never implemented by guessing.
5. **Links**. Fix any other link to the removed files outside `docs/agents/issues/` and `docs/agents/plans/`.

Out of scope: rewriting the historical references in `docs/agents/issues/` and `docs/agents/plans/`, for example in the files of #224 and #228.

## Benefits

- Each client package is described in one place, the as-built docs, so the two descriptions cannot drift apart.
- The client agents read the real, current behaviour, not a pre-implementation plan.
- The `## Specs` section of the docs hub stays ready for the next spec.
