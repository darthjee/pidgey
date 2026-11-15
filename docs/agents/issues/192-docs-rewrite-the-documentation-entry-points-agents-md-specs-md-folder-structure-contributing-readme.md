# Issue: Docs: rewrite the documentation entry points (AGENTS.md, specs.md, folder-structure, contributing, README)

## Description

Part of Epic #185. The spec tree now lives directly under `docs/agents/`, with `architecture` merged and `testing` folded into `contributing`. But the documentation entry points still describe the old model:

- `docs/agents/specs.md` calls the specs "the **source of truth for implementation**" and says they "will later be replaced by documentation of the actual project".
- `AGENTS.md` sends every agent to "Start there", tells them to "Read the specs before writing any code", and enforces the spec granularity rule.

This issue rewrites the five entry points (`AGENTS.md`, `docs/agents/specs.md`, `docs/agents/folder-structure.md`, `docs/agents/contributing.md`, `README.md`) so they describe the docs as documentation of the system **as built**.

## Problem

- `AGENTS.md` still has rules 1, 2 and 4 written for specs: "Read the specs", "Specs are the source of truth" (with a "deployment has no spec" exception), and "Follow the spec granularity rule". It also keeps the whole `## Spec granularity rule` section, with `docs/agents/specs/<name>.md` paths that no longer exist, and the paragraph "These specs describe where the project is going…". Its Documentation table describes `specs.md` as the "source of truth for the intended design".
- `docs/agents/specs.md` claims to be the source of truth and promises a future replacement. It repeats the granularity rule with `specs/` paths, and its Conventions still require "a one-line purpose statement", which the Epic removed. Its `testing` row is malformed (`| Backend| Backend and frontend… |`), and the index lists neither `contributing.md` nor `deployment.md`.
- `docs/agents/folder-structure.md`:
  - it calls the layout "the target defined in [architecture/repository-layout.md]…, which is the source of truth";
  - the `docs/agents/` row says "Entry point is `docs/agents/specs.md`";
  - the `specs.md, specs/` row names a `specs/` folder that is gone and calls it "The source of truth for the intended design";
  - `scripts/` is marked *(planned)* although `scripts/bump-version.sh` exists;
  - `dockerfiles/mysql/` exists but is not listed.
- `docs/agents/contributing.md` opens with "The specs under `docs/agents/specs.md` are the source of truth: when a change alters a decision, update the spec first". Its `### Deployment changes` section opens with "Deployment has no spec."
- `README.md` names `docs/agents/specs.md` as "the entry point" and refers to "the frontend development spec".

## Expected Behavior

- Every entry point says one thing about the docs: the docs in `docs/agents/` describe the system **as built**, and are updated in the same change set as the code. No entry point keeps a "source of truth for the intended design" claim, a promise of future replacement, or the spec granularity rule.
- The `Pending decision` rule stays: an item marked **Pending decision** is never implemented by guessing; it is raised, or the decision is recorded in the doc first.
- Deployment is no longer a special case. The single as-built rule covers it too.
- `docs/agents/specs.md` keeps its filename. It is a plain hub indexing the real docs, and is later meant to be the hub for specs of features not yet built (nothing is created for that now).
- All relative links resolve. No Jasmine `specs/` path changes.

## Solution

1. **`AGENTS.md`**
   - Replace `## Documentation entry point (mandatory)` and its "The entry point is `docs/agents/specs.md`. Start there." with a pointer to the Documentation table below. `docs/agents/specs.md` stays the hub of the area docs.
   - Rewrite `## Rules for agents`:
     - **Read the relevant docs before writing code:** find the area in the Documentation table or the `specs.md` hub, then read its hub and the detail files that apply.
     - **Docs describe the system as built:** when a change alters behaviour or a decision, update the affected docs in `docs/agents/` in the same change set as the code. Drop the deployment exception.
     - **Respect pending decisions:** keep the rule unchanged in meaning; "record the decision in the spec first" becomes "in the doc first".
     - Drop rule 4 (granularity).
   - Delete the `## Spec granularity rule` section and the "These specs describe where the project is going…" paragraph.
   - Turn `## Documentation` into a table of the real docs: `specs.md` (hub of the area docs), `overview.md`, `architecture.md`, `persistence.md`, `request-handler.md`, `docker.md`, `organizations.md`, `email.md`, `api.md`, `frontend.md`, `contributing.md`, `deployment.md`, `folder-structure.md`, `flow.md` (kept as listed today), `plans/` and `issues/`.
     - Reword the `Issues` row ("Detailed specs for open issues") to "Detailed issue files".
     - `issue-enhancement.md` and `arcanum-split-issue.md` stay unlisted.
   - The `### Issues`, `### Plans` and `## Commit Attribution` sections are out of scope.
2. **`docs/agents/specs.md`** keeps its filename and becomes a hub:
   - **Intro:** an index of Pidgey's docs, which describe the system as built. Later, this file is the intended hub for specs of features that are not built yet; none exist now. No source-of-truth claim, and no promise of future replacement. Drop the separate "Deployment is not specified here" paragraph.
   - **Index:**
     - rename the column `Spec` to `Doc`;
     - keep the existing area rows and their descriptions;
     - fix the malformed `testing` row (testing is part of `contributing`);
     - add rows for `contributing.md` and `deployment.md`.
   - Delete `## Spec granularity rule`.
   - **`## Conventions`:**
     - drop "a one-line purpose statement";
     - keep "Undecided items are marked explicitly as **Pending decision**" and "English only";
     - "When a new spec area is added, it must be indexed" becomes "When a new area is added, index it in the table above";
     - add one neutral layout line: each area is a hub `docs/agents/<area>.md` indexing its detail files under `docs/agents/<area>/`. No split rule and no "spec" wording.
3. **`docs/agents/folder-structure.md`** (fix only the stale facts):
   - The intro no longer calls the layout a "target" or [architecture/repository-layout.md](architecture/repository-layout.md) "the source of truth". It describes the current layout, links that file for the detailed layout, and keeps "Entries marked *(planned)* do not exist yet."
   - **Root rows:**
     - `docs/agents/` is "Agent documentation: the area docs, guides, issues and plans", with the hub `docs/agents/specs.md`;
     - remove *(planned)* from `scripts/`;
     - add `dockerfiles/mysql/` to the `dockerfiles/` list;
     - keep *(planned)* on `dev/`, `dockerfiles/pidgey/`, `docker_volumes/configuration/` and `docker_volumes/cache/`, because they do not exist.
   - **`## docs/agents/` table:** replace the `specs.md, specs/` row with rows that match the tree:
     - `specs.md` (the hub);
     - the area hubs `<area>.md` with their `<area>/` folders;
     - the guides (`contributing.md`, `deployment.md`, `folder-structure.md`, `flow.md`);
     - keep `issues/` and `plans/`.
4. **`docs/agents/contributing.md`**:
   - Replace the opening "The specs under `docs/agents/specs.md` are the source of truth…" with the as-built rule: the docs under `docs/agents/` (indexed in [specs.md](specs.md)) describe the system as built, and a change that alters behaviour or a decision updates them in the same change set.
   - In `### Deployment changes`, drop "Deployment has no spec." and keep the rest of the section.
   - The Jasmine-specs sentence stays unchanged.
5. **`README.md`**:
   - "the entry point is `docs/agents/specs.md`" becomes wording such as "start with [`AGENTS.md`](AGENTS.md); the area docs are indexed in [`docs/agents/specs.md`](docs/agents/specs.md)";
   - "frontend development spec" becomes "frontend development doc".
6. **Out of scope:**
   - `docs/agents/deployment.md`'s own "there is no deployment spec" sentence and its `Purpose:` line; the final audit (#194) handles them;
   - `.claude/agents/` (#193);
   - filling `flow.md`;
   - `issues/` and `plans/` content, the frontend `specs/` folders and `.claude/state/*.json`;
   - auditing code against the docs.

### Shared rules (from Epic #185)

- **Zero contract loss:** no endpoint, table, class or method signature, environment variable or error code changes. Do not rewrite prose for style.
- **Every relative link must still resolve.**
- **Never retarget with a blind global replace of `specs/`:** frontend Jasmine paths contain the same string.

### Acceptance Criteria

- [ ] `AGENTS.md` has a Documentation table of the real docs, no spec granularity rule, no "specs are the source of truth" and no deployment exception. The `Pending decision` rule remains.
- [ ] `specs.md` indexes every area hub plus `contributing.md` and `deployment.md`, uses a `Doc` column, has no source-of-truth claim, no promise of future replacement and no granularity rule, and carries the one-line hub + detail-files convention.
- [ ] `folder-structure.md` has no "source of truth" or "target" framing. The `docs/agents/` rows match the tree, `scripts/` is not *(planned)*, and `dockerfiles/mysql/` is listed.
- [ ] `contributing.md` states the as-built rule and has no "Deployment has no spec".
- [ ] `README.md` no longer calls `specs.md` "the entry point" and has no "development spec".
- [ ] All relative links in the changed files resolve.

## Benefits

- Agents and contributors are told the right thing on their first read: the docs describe what exists, and they are kept current with the code.
- One rule covers every doc, deployment included, with no special cases.
- The hub (`specs.md`) and the folder map match the real tree, and the stale `(planned)` markers and the broken `testing` row are fixed.
