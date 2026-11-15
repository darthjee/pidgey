# Plan: Docs: rewrite the documentation entry points (AGENTS.md, specs.md, folder-structure, contributing, README)

Issue: [192-docs-rewrite-the-documentation-entry-points-agents-md-specs-md-folder-structure-contributing-readme.md](../../issues/192-docs-rewrite-the-documentation-entry-points-agents-md-specs-md-folder-structure-contributing-readme.md)

## Overview

A docs-only rewrite of five entry points: `docs/agents/specs.md`, `docs/agents/folder-structure.md`, `docs/agents/contributing.md`, `AGENTS.md` and `README.md`. Afterwards they all state one rule: the docs in `docs/agents/` describe the system **as built** and are updated in the same change set as the code. The rewrite removes:

- the "source of truth for the intended design" claims;
- the promise of a future replacement;
- the spec granularity rule;
- the deployment "has no spec" exception.

The `Pending decision` rule is kept. No file moves, and every link still resolves.

## Context

- Part of Epic #185. #186–#191 have merged, so the area docs live at `docs/agents/<area>.md` + `docs/agents/<area>/`, and `docs/agents/specs/` no longer exists.
- User decisions:
  - drop the deployment exception in `AGENTS.md`/`contributing.md`, and leave `deployment.md`'s own wording to #194;
  - keep a one-line hub + detail-files convention in `specs.md`;
  - the `AGENTS.md` table lists the area hubs and guides, keeps `flow.md`, and leaves `issue-enhancement.md`/`arcanum-split-issue.md` unlisted;
  - `folder-structure.md` fixes stale facts only.
- Facts checked on `main`:
  - `scripts/bump-version.sh` exists;
  - `dockerfiles/mysql/` exists and is not listed;
  - `dev/`, `dockerfiles/pidgey/`, `docker_volumes/configuration/` and `docker_volumes/cache/` do not exist, so they stay *(planned)*;
  - the `specs.md` `testing` row is malformed (`| Backend| Backend and frontend… |`);
  - `.github/` has no "spec" mention.

## Implementation Steps

### Step 1 — Rewrite the docs hub, folder map and contributing rule

1. **`docs/agents/specs.md`** (filename unchanged):
   - Replace the intro paragraphs (including "source of truth for implementation…", "will later be replaced…" and the "Deployment is not specified here" paragraph) with a neutral intro, as sketched in the [Notes](#notes). The intro says:
     - this file indexes Pidgey's docs, which describe the system as built;
     - later, it is the intended hub for specs of features not built yet, and none exist now.
   - **`## Index`:**
     - rename the column header `Spec` → `Doc`;
     - keep every existing area row and description;
     - replace the malformed `testing` row with a `contributing` row (`[contributing](contributing.md)`: commit, PR, code-organization rules, plus testing, linting and definition of done in `contributing/testing.md`), and keep a link to [contributing/testing.md](contributing/testing.md);
     - add a `deployment` row (`[deployment](deployment.md)`: production deployment as implemented: pipeline, variables, server, operations, known gaps).
   - Delete the whole `## Spec granularity rule` section.
   - **`## Conventions`:**
     - delete "Every spec file begins with a title and a one-line purpose statement.";
     - keep the **Pending decision** and "English only" bullets;
     - "When a new spec area is added, it must be indexed in the table above." → "When a new area is added, index it in the table above.";
     - add: "Each area is a hub `docs/agents/<area>.md` indexing its detail files under `docs/agents/<area>/`."
   - Rename the title `# Pidgey Specs` → `# Pidgey Docs`.
2. **`docs/agents/folder-structure.md`:**
   - Intro: drop "the target defined in…, which is the source of truth". Say instead that it shows the current layout, with details in [architecture/repository-layout.md](architecture/repository-layout.md). Keep "Entries marked *(planned)* do not exist yet."
   - Root table:
     - `dockerfiles/` lists `mysql/` too (keep `pidgey/` *(planned)*);
     - drop *(planned)* from `scripts/`, keeping its description;
     - the `docs/agents/` row becomes "Agent documentation: area docs, guides, issues and plans. The area docs are indexed in `docs/agents/specs.md`."
   - `## docs/agents/` table: replace the `specs.md, specs/` row with these rows, and keep the `issues/` and `plans/` rows:
     - `specs.md`: hub indexing the area docs;
     - `<area>.md`, `<area>/`: area hubs and their detail files (overview, architecture, persistence, request-handler, docker, organizations, email, api, frontend, contributing, deployment);
     - `folder-structure.md`, `flow.md`: guides.
3. **`docs/agents/contributing.md`:**
   - Replace "The specs under [`docs/agents/specs.md`](specs.md) are the source of truth: when a change alters a decision, update the spec first, in the same change set." with: "The docs under `docs/agents/` (indexed in [specs.md](specs.md)) describe the system as built: a change that alters behaviour or a decision updates them in the same change set."
   - In `### Deployment changes`, delete "Deployment has no spec." and keep the rest unchanged.
   - Leave the Jasmine sentence (line ~161, "their Jasmine specs mirror…") unchanged.

Commit: `docs: rewrite the specs.md hub, folder map and contributing rule as as-built docs (#192)`.

### Step 2 — Rewrite AGENTS.md and README.md

1. **`AGENTS.md`:**
   - `## Documentation entry point (mandatory)`: keep the heading. Replace the body with "All project documentation lives under `docs/agents/`; see [Documentation](#documentation). The area docs are indexed in [`docs/agents/specs.md`](docs/agents/specs.md)."
   - `## Rules for agents` becomes three rules:
     1. **Read the relevant docs before writing code**: find the area in [Documentation](#documentation) or the `specs.md` hub, then read its hub and the detail files that apply.
     2. **Docs describe the system as built**: when a change alters behaviour or a decision, update the affected docs in `docs/agents/` in the same change set as the code. This drops the deployment exception.
     3. **Respect pending decisions**: items marked "Pending decision" must not be implemented by guessing; raise them or record the decision in the doc first.
   - Delete the `## Spec granularity rule` section and the "These specs describe where the project is going…" paragraph.
   - `## Documentation` table:
     - replace the `Specs` row with `[Docs hub](docs/agents/specs.md)`: index of the area docs, which describe the system as built;
     - add rows for `overview`, `persistence`, `request-handler`, `docker`, `organizations`, `email`, `api` and `frontend`, using the descriptions from `specs.md`;
     - keep the `Folder Structure`, `Architecture`, `Flow`, `Deployment`, `Contributing` and `Plans` rows;
     - change the `Issues` row text "Detailed specs for open issues." → "Detailed issue files.".
   - Leave `### Issues`, `### Plans` and `## Commit Attribution` unchanged.
2. **`README.md`:**
   - "Project documentation lives under [`docs/agents/`](docs/agents/); the entry point is [`docs/agents/specs.md`](docs/agents/specs.md)." → "Project documentation lives under [`docs/agents/`](docs/agents/): start with [`AGENTS.md`](AGENTS.md); the area docs are indexed in [`docs/agents/specs.md`](docs/agents/specs.md).";
   - "development spec" → "development doc".

Commit: `docs: rewrite AGENTS.md and README.md around the as-built docs (#192)`.

## Files to Change

- `docs/agents/specs.md`: hub intro, `Doc` column, fixed `testing`/`contributing` row, new `deployment` row, no granularity rule, updated Conventions (Step 1).
- `docs/agents/folder-structure.md`: intro framing, `dockerfiles/mysql/`, `scripts/` not planned, `docs/agents/` rows (Step 1).
- `docs/agents/contributing.md`: as-built rule, no "Deployment has no spec." (Step 1).
- `AGENTS.md`: entry-point text, three rules, no granularity section, full Documentation table (Step 2).
- `README.md`: entry-point wording, "development doc" (Step 2).

## Notes

- **Sketch of the `specs.md` intro:** "This file indexes Pidgey's documentation. The docs describe the system as built and are updated in the same change set as the code. Later, this hub is also where specs for features not built yet will be indexed; there are none today."
- **Verification greps** over the 5 files:
  - `grep -niE 'source of truth|granularity|will later be replaced|has no spec|no deployment spec'` → empty;
  - `grep -n 'specs/'` → only the Jasmine `frontend/specs/`/`specs/client/` hits in `contributing.md`;
  - `grep -n '(planned)' docs/agents/folder-structure.md` → only `dev/`, `dockerfiles/pidgey/`, `configuration/`, `cache/`;
  - every relative link and `#anchor` in the changed files resolves, in particular `AGENTS.md#documentation`, `contributing.md#deployment-changes` and `contributing/testing.md`.
- `.claude/agents/architect.md` also points at the docs, but it belongs to #193; do not touch it here.
- `deployment.md`'s "there is no deployment spec" sentence and its `Purpose:` line are left for #194; mention this in the PR description.
- Zero contract loss: review the diff, so that only entry-point wording, rules, index rows and the stale folder facts change.
