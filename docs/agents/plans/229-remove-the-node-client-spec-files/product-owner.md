# Plan: Remove the Node client spec files

Issue: [229-remove-the-node-client-spec-files.md](../../issues/229-remove-the-node-client-spec-files.md)

## Overview

This is a docs-only cleanup at the end of epic #219. The specs say it should happen: see the Lifecycle section of `docs/agents/specs/node-client.md`. The work has three parts:

- check that the as-built client docs cover every rule of the specs;
- delete the specs and the `node-client` row of the docs hub;
- repoint the `nodejs` and `cli` agents at the as-built docs.

## Context

- Spec files to remove:
  - the hub `docs/agents/specs/node-client.md`;
  - `docs/agents/specs/node-client/`, which holds `library.md`, `errors-and-retry.md`, `cli.md`, `openapi.md`, `packaging.md` and `testing.md`.
- The as-built docs are written by the implementation sub-issues #244 to #254:
  - `docs/agents/clients.md`, `docs/agents/clients/nodejs.md` and `docs/agents/clients/cli.md`;
  - the OpenAPI route and the additive-only rule under `docs/agents/api/`;
  - CI jobs and publishing in `docs/agents/deployment/` and `docs/agents/contributing.md`.
- No **Pending decision** is open in the specs.
- References to the specs outside history, found when this plan was written:
  - `docs/agents/specs.md`: the `## Specs` table holds a single `node-client` row;
  - `.claude/agents/nodejs.md:53` and `.claude/agents/cli.md:61`: the reading-order lines.
- References inside `docs/agents/issues/` and `docs/agents/plans/` are history and stay untouched.

## Steps

- [01 — Audit the specs against the as-built docs](product-owner/01-audit-specs.md)
- [02 — Delete the specs and update the docs hub](product-owner/02-delete-specs-and-update-hub.md)
- [03 — Repoint the agent files and fix remaining links](product-owner/03-repoint-agents-and-links.md)

## CI Checks

No CI job covers the changed paths, which are docs and agent definitions only. The local check is:

```bash
grep -rn "specs/node-client" . --exclude-dir=.git --exclude-dir=node_modules \
  | grep -v "^./docs/agents/issues/\|^./docs/agents/plans/"
```

It must return nothing.

## Notes

- **Ownership.** Some edits fall outside `product-owner`'s scope, and `architect` must make them:
  - step 03's `.claude/agents/` edits;
  - any rule that step 01 has to move into `docs/agents/deployment/` or `docs/agents/contributing.md`.

  `architect` is the coordinator, so it applies these itself during `auto-fix-issue`, as the issue's co-owner.
- **Blocked.** This issue runs only once every other sub-issue of #219 is closed. If `docs/agents/clients*.md` does not exist yet when work starts, stop: the blocker is not cleared.
- **Audit result.** The spec-section to as-built-doc mapping goes in the PR description, not in a committed file.
