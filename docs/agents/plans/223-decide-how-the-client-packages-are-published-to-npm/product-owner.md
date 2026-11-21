# Product Owner Plan: Decide how the client packages are published to npm

Main plan: [plan.md](plan.md)

## Overview

Record the decisions of issue #223 in `docs/agents/specs/node-client/`. Nothing outside the specs changes: no `.circleci/config.yml`, no script, no `docs/agents/deployment/` file (those belong to the implementation sub-issue of #219 that builds the pipeline).

## Context

- The issue file's **Solution** section is the source of every rule below; transcribe it, do not reinvent it.
- `packaging.md` currently has, under `## Publishing`, a "security group" bullet and the `**Pending decision** (#223)` marker; its `### Supply chain` section has the "2FA and token" and "Provenance" bullets that defer to #223, and its `### CLI to library dependency` section (from #222) has the registry check and "What the publish procedure must do".
- `docs/agents/deployment/pipeline.md` is the precedent to link to (build jobs vs context jobs, context restriction, serial group); it states security groups are not available on the free plan.

## Implementation Steps

### Step 1 — `packaging.md`: the publishing spec

In `docs/agents/specs/node-client/packaging.md`:

1. **`## Publishing`:**
   - Replace the "Already fixed … same security group as `pidgey-deploy`" bullet: whoever can publish is whoever can push a package tag (today only the owner), and the credential sits in a `pidgey-npm` context with an **expression restriction** (no security group: none on the free plan, see `../../deployment/pipeline.md#context-restriction`).
   - Replace the `**Pending decision** (#223)` paragraph with subsections, following the issue's Solution:
     - **Mechanism** (CircleCI on `pidgey-client-vX.Y.Z` / `pidgey-cli-vX.Y.Z` tags, filters, never matching the backend's `^\d+\.\d+\.\d+$`), with the rejected options (navi model, manual publish, GitHub Actions) and why;
     - **CI jobs** table (`pack_client`/`pack_cli` with no context; `publish_client`/`publish_cli` in `pidgey-npm`, `serial-group …/pidgey-npm`, idempotent no-op when the version is already on npm, `npm publish <tarball> --access public --ignore-scripts`, wait until npm serves the version);
     - **Both packages in one release** (CLI polls the floor every 10 s for up to 5 min, then aborts);
     - **Credential** (`pidgey-npm` holding only `NPM_TOKEN`, restriction expression `pipeline.git.tag =~ "^pidgey-(client|cli)-v[0-9]+\.[0-9]+\.[0-9]+$"`, attached only to the publish jobs, granular token scoped to both packages with an expiry, 2FA settings, rotation, never echoed);
     - **Pre-publish gate** (the five conditions);
     - **Release procedure** (release PR, tag on `main`, CI publishes);
     - **Rollback** (`npm deprecate` by hand with 2FA + fix-forward patch; unpublish only for a leaked secret within 72 h; no dist-tags or pre-releases on `0.x`);
     - **Docs for the implementation sub-issue** (`pipeline.md`, `variables.md`, `operations.md` under `docs/agents/deployment/`, owned by `architect`).
   - Mark the two facts the implementation sub-issue must confirm: npm's write-token expiry cap (90 days) and setting names, and CircleCI support for provenance / trusted publishing.
2. **`### CLI to library dependency`:** the registry-check bullet now says the check **waits** (polls up to 5 min) before aborting, linking to the Publishing section; "What the publish procedure must do" links to the new section instead of naming #223.
3. **`### Supply chain`:** the "2FA and token" bullet links to the Credential subsection instead of the pending decision; the "Provenance" bullet links to the Provenance rule (supported → `--provenance` or OIDC; not supported → the gap is recorded here) instead of "#223 confirms feasibility"; the "Range versus pin" bullet refers to the publishing controls of this file, not #223.

### Step 2 — Hub and `testing.md`

1. `docs/agents/specs/node-client.md`: remove the `#223` row from the Pending decisions table (keep `#226`).
2. `docs/agents/specs/node-client/testing.md`: replace "**Publish gate:** the gate itself belongs to #223." with a link to the pre-publish gate in `packaging.md`. Mention there that the publish job re-uses the tarball the pack job built and checked.
3. Grep `docs/agents/specs/` for any remaining `#223` mention and point it at the new section; no `**Pending decision** (#223)` marker may remain.

## Files to Change

- `docs/agents/specs/node-client/packaging.md` — Publishing section, CLI-to-library registry check, Supply chain bullets.
- `docs/agents/specs/node-client.md` — drop the #223 pending row.
- `docs/agents/specs/node-client/testing.md` — publish-gate link.

## CI Checks

- Docs only: no CI job covers `docs/agents/specs/` beyond the general checks; run `make tests` only if the repo's docs lint applies.

## Notes

- Keep the wording plain and short, like the #222 section; tables for jobs, numbered lists for the procedure.
- Do not create or edit anything under `docs/agents/deployment/` or `.circleci/`: that is the implementation sub-issue's work.
