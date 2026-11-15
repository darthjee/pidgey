# Issue: Docs: merge the architecture spec hub into docs/agents/architecture.md

## Description
Part of Epic #185. Merge the leftover spec hub `docs/agents/specs/architecture.md` (an 11-line index of the five `architecture/*` detail files) into `docs/agents/architecture.md`, make that file authoritative, and delete the last file under `docs/agents/specs/`, which removes the folder. Depends on #186 (merged). Runs in parallel with #188–#191.

## Problem
After #186, `docs/agents/specs/` holds only `architecture.md`, kept because its path collided with the existing `docs/agents/architecture.md`. That summary file still opens with a banner saying it only summarizes the target architecture and that "the spec wins" on disagreement. It also marks `source/` and `frontend/` as `*planned*`, though both exist. Two docs in `architecture/*` still call themselves "specs".

## Expected Behavior
- A single hub `docs/agents/architecture.md`: summary plus the index of `architecture/*`, with no statement that it is subordinate to any spec.
- Nothing from the old summary or the old spec hub is lost.
- `docs/agents/specs/` no longer exists; `docs/agents/specs.md` still exists.
- Every relative link resolves.
- `architecture/*` wording and Pending decisions reviewed per the rules below.

## Solution
### Changes
- **`docs/agents/architecture.md`**
  - Replace the banner with a `## Detail files` section right after the `# Architecture` title, carrying the same File / Content table as the old spec hub, with links `architecture/<file>.md`.
  - Keep Overview and Source Code Layout as they are; drop the `*planned*` markers on `source/` and `frontend/`. The component-map link stays on `overview/components.md` (no diagram duplication).
- **`docs/agents/specs/architecture.md`**: delete it, which removes `docs/agents/specs/`.
- **`docs/agents/specs.md`**: retarget the `architecture` row link from `specs/architecture.md` to `architecture.md` (path only; rewriting `specs.md` is #192).
- **`architecture/repository-layout.md`**: the `docs/agents/` row becomes "Agent documentation, starting at `AGENTS.md`."
- **`architecture/dependencies.md`**: "requires updating this spec first" becomes "requires updating this doc first".
- **`architecture/configuration.md`**: its `## Pending decisions` reads "None. …" and stays unchanged.

The only inbound links to `specs/architecture.md` outside `issues/` and `plans/` are the two above; no anchors target it. No `planned`/`target`/`intended` markers exist in the five detail files.

### Wording cleanup rules
- Remove target-state markers ("planned", "*planned*", "target", "will later be replaced", "intended design") where the artifact already exists.
- Replace self-references to "specs" describing these files as the spec tree with neutral wording ("docs"). Do not touch Jasmine "specs" (frontend test files).
- Do not move or rename files; paths and anchors were fixed by #186.

### Pending decision review rules
1. An item counts as **decided** only on evidence in the repo docs or issues (another doc states the choice, a closed issue settled it, or the file documents it as implemented). No code audit. When unclear, it stays Pending.
2. A decided item moves into the contract text and leaves the Pending list; mirrored summaries are updated together.
3. "None." and pointer-only `## Pending decisions` sections stay.
4. No new GitHub issue per open item.

### Shared rules (from Epic #185)
- **Zero contract loss:** endpoints, tables/columns, class and method signatures, environment variables, error codes and failure taxonomy are preserved intact. Do not rewrite prose for style.
- **Never retarget with a blind global replace of `specs/`:** frontend Jasmine paths contain the same string.
- Out of scope: `docs/agents/issues/`, `docs/agents/plans/`, the frontend `specs/` folders, `.claude/state/*.json`, and auditing code against docs.

### Ownership and testing
- `architect` owns all touched files (single PR).
- No dedicated test strategy: the acceptance criteria plus a check that every relative link under `docs/agents/`, `README.md` and `.claude/agents/` resolves.

## Benefits
- `docs/agents/specs/` disappears, finishing the physical fold of the spec tree.
- `architecture.md` becomes the single authoritative architecture hub, with no conflicting "the spec wins" rule.
