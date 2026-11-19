# Plan: Write the Node client specs

Issue: [224-write-the-node-client-specs.md](../../issues/224-write-the-node-client-specs.md)

## Overview
Create the Node client specs of epic #219 as a hub plus six topic files, transcribing the epic's Proposal, Design decisions, Alternatives considered, Edge cases, Implementation notes and Testing sections. Docs only: no code, CI, agent definition or as-built area doc changes.

## Context
- Source material: the body of GitHub issue #219 ("Create Clients") — read it with `gh issue view 219`. From the merge of this change on, the spec files (not the epic body) are the source of truth.
- `docs/agents/specs.md` says specs of features not built yet are indexed there; there are none today and `docs/agents/specs/` does not exist.
- Existing hubs (for example `docs/agents/api.md`) are a `# Title` followed by a `| File | Content |` table; follow that shape for the new hub.
- Open items are decided by #220 (extra test checks), #222 (CLI → library dependency), #223 (publishing), #225 (performance and security) and #226 (Node.js version). #221 (Idempotency-Key) is related but outside the epic.
- Conventions fixed by the issue:
  - Open items: `**Pending decision** (#<issue>): <one-line question>`, in the file where the decision will land; the hub lists them all.
  - Conflicts between #219 and as-built docs (`docs/agents/api/`, `docs/agents/email/`): the as-built docs win for current behaviour; the conflict is listed in the PR description, not silently resolved.
  - Examples use placeholder tokens (`pdg_example…`) and `example.com` hosts only.
  - Each file covers one topic; split further under `docs/agents/specs/node-client/` if a file starts mixing topics, and index it from the hub.

## Steps

- [01 — Hub and specs index](product-owner/01-hub-and-specs-index.md)
- [02 — Library and errors/retry specs](product-owner/02-library-and-errors-retry.md)
- [03 — CLI spec](product-owner/03-cli.md)
- [04 — OpenAPI spec](product-owner/04-openapi.md)
- [05 — Packaging and testing specs](product-owner/05-packaging-and-testing.md)
- [06 — Consistency pass](product-owner/06-consistency-pass.md)

## CI Checks
No CI job covers `docs/` content; the change touches no `source/`, `frontend/` or `dockerfiles/` path, so `backend_checks`, `backend_tests` and `frontend_checks` are unaffected.

## Notes
- Do not create `docs/agents/clients.md` or `docs/agents/clients/`: the as-built docs are written later by the implementation sub-issues.
- Do not edit `AGENTS.md`, `folder-structure.md` or `.claude/agents/` (owned by `architect`, handled by a later sub-issue).
- The spec files are temporary; #229 removes them at the end of the epic.
