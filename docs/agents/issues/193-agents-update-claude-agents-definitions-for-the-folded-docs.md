# Issue: Agents: update .claude/agents definitions for the folded docs

## Description

Part of Epic #185. The spec tree is now folded into `docs/agents/`, and the entry points (`AGENTS.md`, `specs.md`, `folder-structure.md`, `contributing.md`, `README.md`) were rewritten in #192 to describe the docs as the system **as built**. The specialist agent definitions in `.claude/agents/` still encode the old spec model: they call the specs the source of truth, point at a `docs/agents/specs/` tree that no longer exists, and assign ownership by that tree. This issue updates their rules and ownership so agents behave consistently with the new docs.

## Problem

- **`architect.md`**:
  - its scope reads "`docs/agents/` — except `specs.md`, `specs/` and `issues/`, which belong to `product-owner`", and the `product-owner` row of the agents table names `docs/agents/specs/`;
  - "How to coordinate" step 3 says "specs are the source of truth … have `product-owner` update the spec first";
  - its Documentation table calls `specs.md` the "source of truth for the intended design", says "Deployment has no spec", describes `issues/` as "Detailed specs for open issues", and lists none of the area docs.
- **`product-owner.md`**:
  - its description says "writing/updating specs under docs/agents/specs/";
  - its scope lists `docs/agents/specs/`;
  - it keeps the specs "the **source of truth**", follows the spec granularity rule (`specs/<name>.md` → `specs/<name>/*.md`), requires a one-line `Purpose:` statement, and says "Every new spec area is indexed in `docs/agents/specs.md`".
- **`backend.md`**, **`frontend.md`**, **`infra.md`**: each has "Do NOT touch … `docs/agents/specs/` (`product-owner`)".
  - `backend.md`: "Read the relevant specs (`docs/agents/specs.md`) before coding; specs are the source of truth", and "update the spec before adding any" dependency.
  - `frontend.md`: "Read the relevant specs before coding — `docs/agents/frontend.md` …".
  - `infra.md`: "there is no deployment spec" and "a **Pending decision** in the docker specs".
- **`email.md`**: "`product-owner` (specs in `docs/agents/email/`)".
- **`security.md`**: no spec-model wording (its `architecture/dependencies.md` link is current).

## Expected Behavior

- Every agent definition says the same thing as `AGENTS.md`: the docs in `docs/agents/` describe the system as built; read the relevant area docs before coding and update them in the same change set as the code.
- The `Pending decision` rule stays in every agent that has it: never implement one by guessing.
- No agent definition calls the docs the source of truth, references `docs/agents/specs/` as an existing tree, mentions the spec granularity rule or the `Purpose:` line, or makes deployment a special case.
- Ownership of every doc is explicit and consistent across `architect.md`, `product-owner.md` and the "Do NOT touch" lines:
  - `product-owner` owns the area docs (`overview`, `persistence`, `request-handler`, `docker`, `organizations`, `email`, `api`, `frontend`, each hub `.md` with its folder), `specs.md` and `issues/`;
  - `architect` owns the rest of `docs/agents/`: `architecture.md` and `architecture/`, `contributing.md` and `contributing/`, `deployment.md` and `deployment/`, `folder-structure.md`, `flow.md`, `plans/`, `issue-enhancement.md`, `arcanum-split-issue.md`.
- The frontend's Jasmine "specs" wording is untouched.

## Solution

1. **`.claude/agents/architect.md`**
   - **Scope:** `docs/agents/` except the area docs (`overview`, `persistence`, `request-handler`, `docker`, `organizations`, `email`, `api`, `frontend`, each `<area>.md` with `<area>/`), `specs.md` and `issues/`, which belong to `product-owner`. List what architect owns: `plans/`, `architecture.md` and `architecture/`, `contributing.md` and `contributing/`, `deployment.md` and `deployment/`, `folder-structure.md`, `flow.md`, `issue-enhancement.md`, `arcanum-split-issue.md`.
   - **Agents table:** the `product-owner` row becomes the area docs, `docs/agents/specs.md` and `docs/agents/issues/` (area docs and issue definitions).
   - **"How to coordinate" step 3:** "Check the docs — the docs describe the system as built. If a task changes behaviour or a decision, have the owner of the affected docs update them in the same change set. Never let a specialist implement a **Pending decision** by guessing."
   - **Documentation table:** mirror the `AGENTS.md` Documentation table row for row (Docs hub, the 8 area docs, Folder Structure, Architecture, Flow, Deployment, Contributing, Plans, Issues), with the same descriptions and an `Owner` column per the split above. The Deployment row drops "Deployment has no spec"; the Issues row reads "Detailed issue files.". `issue-enhancement.md` and `arcanum-split-issue.md` stay listed only in the scope section.
2. **`.claude/agents/product-owner.md`**
   - **Description:** "…defining or changing requirements, writing/updating the area docs under docs/agents/, recording decisions on Pending items, and writing issue files under docs/agents/issues/."
   - **Scope:** `docs/agents/specs.md` (the docs hub), the area docs (each `docs/agents/<area>.md` hub with its `docs/agents/<area>/` detail files, listing the 8 areas), and `docs/agents/issues/`. The "Do NOT touch" line names the architect-owned docs (`plans/`, `architecture*`, `contributing*`, `deployment*`, the guides).
   - **Responsibilities:** replace "Keep the specs the source of truth…" with "The area docs describe the system as built: when behaviour or a decision changes, update them in the same change set as the code." Keep the what/why line and the Pending decision line. "When a spec changes, check the other specs…" becomes "docs".
   - **Conventions:** drop the granularity rule and the `Purpose:` line. Add the layout line: each area is a hub `docs/agents/<area>.md` indexing its detail files under `docs/agents/<area>/`. "Every new spec area is indexed" becomes "Every new area is indexed in `docs/agents/specs.md`; future specs of features not yet built are also indexed from there." Keep "English only".
3. **`.claude/agents/backend.md`**
   - "Do NOT touch" line: `docs/agents/specs/` (`product-owner`) becomes `docs/agents/` (`product-owner` for the area docs, `architect` for the rest).
   - Dependencies line: "update the spec before adding any" becomes "update it in the same change set before adding any".
   - Conventions: "Read the relevant docs (indexed in `docs/agents/specs.md`) before coding; they describe the system as built and are updated in the same change set as the code. Never implement a **Pending decision** by guessing."
4. **`.claude/agents/frontend.md`**
   - Same "Do NOT touch" change as `backend.md`.
   - "Read the relevant specs before coding" becomes "Read the relevant docs before coding", with the same file list, plus "update them in the same change set as the code".
   - Every Jasmine "specs" use (`specs/` folder, `*Spec.js`, `yarn test`, "Write Jasmine specs") stays unchanged.
5. **`.claude/agents/infra.md`**
   - Same "Do NOT touch" change as `backend.md`.
   - Drop "; there is no deployment spec"; "in the docker specs" becomes "in the docker docs". The rest of the line (known gaps, `docs/agents/deployment/` owned by `architect`) stays.
6. **`.claude/agents/email.md`**: "`product-owner` (specs in `docs/agents/email/`)" becomes "`product-owner` (docs in `docs/agents/email.md` and `docs/agents/email/`)".
7. **`.claude/agents/security.md`**: no change needed.

### Shared rules (from Epic #185)

- **Zero contract loss:** no change to scopes beyond the ownership split above, and no rewriting of prose for style.
- **Every relative link must still resolve** (the `../../docs/agents/...` links in `architect.md`).
- **Never retarget with a blind global replace of `specs/`:** the frontend Jasmine paths contain the same string.
- **Out of scope:** `docs/agents/issues/`, `docs/agents/plans/`, the frontend `specs/` folders, `.claude/state/*.json`, auditing code against the docs, and the final audit items of #194 (leftover `Purpose:` lines in docs, `deployment.md`'s "no deployment spec" sentence).

### Acceptance Criteria

- [ ] No agent definition describes the docs as the source of truth or references `docs/agents/specs/` as an existing tree; no granularity rule or `Purpose:` convention remains.
- [ ] Ownership of every doc is explicit and consistent across `architect.md`, `product-owner.md` and the "Do NOT touch" lines in `backend.md`, `frontend.md` and `infra.md`; `architecture/` belongs to `architect`.
- [ ] `architect.md`'s Documentation table mirrors `AGENTS.md`'s, with an `Owner` column.
- [ ] The `Pending decision` rule is preserved.
- [ ] Jasmine "specs" wording in `frontend.md` is untouched.
- [ ] All relative links in `architect.md` resolve.

## Benefits

- Agents follow the same rule as `AGENTS.md`: the docs describe what exists and are updated with the code.
- Every doc has one explicit owner, so delegation never sends work to a non-existent `specs/` tree.
- The architect's Documentation table indexes every doc, matching the entry point.
