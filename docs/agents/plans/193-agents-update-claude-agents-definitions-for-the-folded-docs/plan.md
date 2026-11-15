# Plan: Agents: update .claude/agents definitions for the folded docs

Issue: [193-agents-update-claude-agents-definitions-for-the-folded-docs.md](../../issues/193-agents-update-claude-agents-definitions-for-the-folded-docs.md)

## Overview

Rewrite the rules and ownership in the `.claude/agents/` definitions so they match the as-built docs model that `AGENTS.md` now states (#192): the docs in `docs/agents/` describe the system as built and are updated in the same change set as the code. Ownership moves from the old `specs.md` + `specs/` tree to an explicit split: `product-owner` owns the eight area docs, `specs.md` and `issues/`; `architect` owns the rest of `docs/agents/`, including `architecture/`. `.claude/` is architect-owned, so the whole change is cross-cutting work for the architect (no specialist has work).

## Context

- `docs/agents/specs/` no longer exists; the area hubs and folders now sit directly under `docs/agents/` (`overview`, `persistence`, `request-handler`, `docker`, `organizations`, `email`, `api`, `frontend`). `architecture` merged into `docs/agents/architecture.md` + `architecture/`; testing folded into `contributing.md` + `contributing/`.
- `AGENTS.md` (after #192) has three rules (read the relevant docs; docs describe the system as built; respect pending decisions) and a Documentation table listing the Docs hub, the 8 area docs, Folder Structure, Architecture, Flow, Deployment, Contributing, Plans and Issues.
- User decisions: `architecture/` belongs to `architect`; `architect.md`'s Documentation table mirrors `AGENTS.md`'s with an `Owner` column.
- Epic rules: zero contract loss, no prose rewritten for style, every relative link resolves, never blind-replace `specs/` (Jasmine paths in `frontend.md`).

## Implementation Steps

### Step 1 — Ownership: `architect.md` and `product-owner.md`

**`.claude/agents/architect.md`**

- **Your scope** (line 11–13): replace the "`docs/agents/` — except `specs.md`, `specs/` and `issues/`…" bullet with: `docs/agents/` — except the area docs (`overview`, `persistence`, `request-handler`, `docker`, `organizations`, `email`, `api`, `frontend`: each `<area>.md` hub with its `<area>/` folder), `specs.md` and `issues/`, which belong to `product-owner`. Then list what architect owns: `plans/`, `architecture.md` and `architecture/`, `contributing.md` and `contributing/`, `deployment.md` and `deployment/`, `folder-structure.md`, `flow.md`, `issue-enhancement.md`, `arcanum-split-issue.md`.
- **Agents table** (`product-owner` row): `docs/agents/specs.md`, the area docs (`docs/agents/<area>.md` + `docs/agents/<area>/`), `docs/agents/issues/` — area docs and issue definitions.
- **How to coordinate, step 3:** `**Check the docs** — the docs describe the system as built. If a task changes behaviour or a decision, have the owner of the affected docs update them in the same change set as the code. Never let a specialist implement a **Pending decision** by guessing.`
- **Documentation table:** rebuild with columns `File | Contents | Owner`, one row per row of `AGENTS.md`'s Documentation table, in the same order and with the same Contents text, links rewritten to `../../docs/agents/...`:
  - Docs hub (`specs.md`), Overview, Persistence, Request Handler, Docker, Organizations, Email, API, Frontend → `product-owner`;
  - Folder Structure, Architecture, Flow, Deployment, Contributing, Plans → `architect`;
  - Issues ("Detailed issue files.") → `product-owner`.
  The Deployment row uses the `AGENTS.md` wording (no "Deployment has no spec").
- Keep the closing "Keep documentation up to date…" paragraph.

**`.claude/agents/product-owner.md`**

- **description** (frontmatter): `Pidgey product owner. Use for defining or changing requirements, writing/updating the area docs under docs/agents/, recording decisions on Pending items, and writing issue files under docs/agents/issues/.`
- **Your scope:** replace the two `specs.md` / `specs/` bullets with:
  - `docs/agents/specs.md` — the docs hub
  - the area docs — each `docs/agents/<area>.md` hub with its `docs/agents/<area>/` detail files, for `overview`, `persistence`, `request-handler`, `docker`, `organizations`, `email`, `api`, `frontend`
  - keep the `docs/agents/issues/` bullet.
  - The "Do NOT touch" line names the architect-owned docs explicitly: `docs/agents/plans/`, `architecture.md`/`architecture/`, `contributing.md`/`contributing/`, `deployment.md`/`deployment/`, and the other guides.
- **Responsibilities:** first bullet becomes `The area docs describe the system as built: when behaviour or a decision changes, update them in the same change set as the code.` Keep the what/why bullet and the Pending decision bullet verbatim. "When a spec changes, check the other specs that reference it…" → "When a doc changes, check the other docs that reference it…".
- **Conventions:** remove the granularity-rule bullet and the `Purpose:` bullet; add `Each area is a hub docs/agents/<area>.md indexing its detail files under docs/agents/<area>/.`; replace "Every new spec area is indexed in `docs/agents/specs.md`." with `Every new area is indexed in docs/agents/specs.md; specs of features not built yet will also be indexed from there.`; keep "English only."

Commit: `docs(agents): move architect and product-owner ownership to the folded docs (#193)`

### Step 2 — Rules: `backend.md`, `frontend.md`, `infra.md`, `email.md`

**`.claude/agents/backend.md`**

- Line 20: `` `docs/agents/specs/` (`product-owner`) `` → `` `docs/agents/` (area docs: `product-owner`; the rest: `architect`) ``.
- Line 26: "update the spec before adding any" → "update it in the same change set before adding any".
- Line 45: `Read the relevant docs (indexed in docs/agents/specs.md) before coding; they describe the system as built, so update them in the same change set as the code. Never implement a **Pending decision** by guessing.`

**`.claude/agents/frontend.md`**

- Line 20: same "Do NOT touch" change as `backend.md`.
- Line 153: "Read the relevant specs before coding — …" → "Read the relevant docs before coding — `docs/agents/frontend.md` (index) and the relevant `docs/agents/frontend/*.md` files, plus `docs/agents/api.md`; update them in the same change set as the code. Never implement a **Pending decision** by guessing."
- Leave lines 17, 37–38, 78, 82–85, 117, 137, 155 (Jasmine specs) untouched.

**`.claude/agents/infra.md`**

- Line 21: same "Do NOT touch" change as `backend.md`.
- Line 41: drop "; there is no deployment spec" from the parenthesis; "in the docker specs" → "in the docker docs". Everything else unchanged.

**`.claude/agents/email.md`**

- Line 7: "`product-owner` (specs in `docs/agents/email/`)" → "`product-owner` (docs in `docs/agents/email.md` and `docs/agents/email/`)".

`security.md`: no change.

Commit: `docs(agents): align specialist rules with the as-built docs (#193)`

## Files to Change

- `.claude/agents/architect.md` — scope, agents table, coordination step 3, Documentation table with Owner column.
- `.claude/agents/product-owner.md` — description, scope, responsibilities, conventions.
- `.claude/agents/backend.md` — Do NOT touch line, dependency line, read-docs convention.
- `.claude/agents/frontend.md` — Do NOT touch line, read-docs convention (Jasmine wording untouched).
- `.claude/agents/infra.md` — Do NOT touch line, deployment/docker wording.
- `.claude/agents/email.md` — product-owner docs reference.

## Notes

- Verification after both steps:
  - `grep -n "docs/agents/specs/" .claude/agents/*.md` → no matches.
  - `grep -n -i "source of truth\|granularity\|Purpose:\|no deployment spec\|has no spec" .claude/agents/*.md` → no matches.
  - `grep -n -i "pending decision" .claude/agents/*.md` → still present in architect, product-owner, backend, frontend, infra.
  - `git diff -U0 .claude/agents/frontend.md` touches only lines 20 and 153.
  - Every `../../docs/agents/...` link in `architect.md` resolves (check with `ls` from `.claude/agents/`).
- Out of scope: leftover `Purpose:` lines inside `docs/agents/` and `deployment.md`'s "no deployment spec" sentence (#194); `docs/agents/issues/`, `plans/`, frontend `specs/` folders, `.claude/state/*.json`.
