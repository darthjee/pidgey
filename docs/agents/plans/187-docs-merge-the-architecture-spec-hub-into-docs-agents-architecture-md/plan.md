# Plan: Docs: merge the architecture spec hub into docs/agents/architecture.md

Issue: [187-docs-merge-the-architecture-spec-hub-into-docs-agents-architecture-md.md](../../issues/187-docs-merge-the-architecture-spec-hub-into-docs-agents-architecture-md.md)

## Overview

Fold the last file under `docs/agents/specs/` (`architecture.md`, an index of the five
`architecture/*` detail files) into `docs/agents/architecture.md`, make that file the
authoritative hub, delete `docs/agents/specs/`, and clean up the "spec" wording left in
`docs/agents/architecture/*`. Docs only; all commits by `architect`, single PR.

## Context

Part of Epic #185; #186 (the move) is merged. After the move:

- `docs/agents/specs/architecture.md` is the only file left in `docs/agents/specs/`.
- Only two links target it outside `docs/agents/issues/` and `docs/agents/plans/`:
  the banner in `docs/agents/architecture.md` (line 4) and the `architecture` row in
  `docs/agents/specs.md` (line 19). No anchors target it.
- `docs/agents/architecture.md` opens with a banner subordinating it to the specs and marks
  `source/` and `frontend/` as `*planned*`.
- `architecture/repository-layout.md:13` and `architecture/dependencies.md:29` still refer
  to "specs" / "this spec".
- `architecture/configuration.md`'s `## Pending decisions` reads "None. …" and stays.

## Implementation Steps

### Step 1 — Merge the hub and delete `docs/agents/specs/`

In `docs/agents/architecture.md`:

- Remove the three-line blockquote banner ("This document summarizes the target
  architecture… the spec wins.").
- In its place, right after `# Architecture`, add:

  ```markdown
  Index of the architecture docs: repository layout, backend structure, database access,
  dependency policy and configuration. The sections below summarize the architecture; the
  detail files hold the full contract.

  ## Detail files

  | File | Content |
  | --- | --- |
  | [repository-layout.md](architecture/repository-layout.md) | Top-level folders and what goes in each. |
  | [backend.md](architecture/backend.md) | Plain PHP backend: namespace, front controller, layers. |
  | [database.md](architecture/database.md) | MySQL 9 via raw PDO; migrations strategy. |
  | [dependencies.md](architecture/dependencies.md) | Runtime vs dev dependency policy. |
  | [configuration.md](architecture/configuration.md) | Every environment variable, its default and effect. |
  ```

  (Table rows copied verbatim from `specs/architecture.md`, links rebased from
  `../architecture/` to `architecture/`.)
- In `## Source Code Layout`, drop the two ` (*planned*)` markers:
  "All backend source code lives under `source/`. Frontend code lives under `frontend/`."
- Leave every other section (Overview, all `###` subsections, the
  `overview/components.md` link) unchanged.

Then:

- `git rm docs/agents/specs/architecture.md` — `docs/agents/specs/` disappears.
- `docs/agents/specs.md` line 19: change `[architecture](specs/architecture.md)` to
  `[architecture](architecture.md)`. Path only; no other edit to `specs.md` (#192 owns it).

Commit: `docs: merge architecture spec hub into architecture.md (#187)`.

### Step 2 — Wording cleanup in `docs/agents/architecture/*`

- `architecture/repository-layout.md:13`: the `docs/agents/` row content
  "Specs and agent documentation, starting at `docs/agents/specs.md`." becomes
  "Agent documentation, starting at `AGENTS.md`."
- `architecture/dependencies.md:29`: "Adding any new runtime dependency requires updating
  this spec first." becomes "… requires updating this doc first."
- `architecture/configuration.md`: no change (the "None." Pending section stays).
- Re-grep `docs/agents/architecture.md` and `docs/agents/architecture/*.md` for
  `planned|target|intended|will later|spec` and confirm nothing else needs the wording
  rules (expected: none; "source of truth" in `backend.md` and "specified in" phrasing are
  fine and stay).

Commit: `docs: drop spec wording from architecture docs (#187)`.

## Files to Change

- `docs/agents/architecture.md` — replace banner with intro + `## Detail files` table; drop `*planned*` markers.
- `docs/agents/specs/architecture.md` — delete (removes `docs/agents/specs/`).
- `docs/agents/specs.md` — retarget the `architecture` row link to `architecture.md`.
- `docs/agents/architecture/repository-layout.md` — reword the `docs/agents/` row.
- `docs/agents/architecture/dependencies.md` — "this spec" → "this doc".

## Verification

- `test ! -e docs/agents/specs && test -f docs/agents/specs.md`.
- No remaining reference: `grep -rn "specs/architecture" docs/agents README.md .claude/agents --include=*.md | grep -v "docs/agents/issues\|docs/agents/plans"` returns nothing.
- Every relative markdown link in `docs/agents/` (excluding `issues/`, `plans/`), `README.md`
  and `.claude/agents/` resolves to an existing file (a small script resolving
  `](path)` / `](path#anchor)` targets relative to each file is enough).

## Notes

- Do not use a global replace of `specs/`: frontend Jasmine paths share the string.
- Zero contract loss: no other prose in these files is rewritten.
- No CI job checks docs; verification is the local link check above.
