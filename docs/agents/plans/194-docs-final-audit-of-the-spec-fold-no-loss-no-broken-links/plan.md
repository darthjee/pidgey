# Plan: Docs: final audit of the spec fold (no loss, no broken links)

Issue: [194-docs-final-audit-of-the-spec-fold-no-loss-no-broken-links.md](../../issues/194-docs-final-audit-of-the-spec-fold-no-loss-no-broken-links.md)

## Overview

Last sub-issue of Epic #185. Audit the fold of `docs/agents/specs/` into `docs/agents/`
(renames, contract counts, references, links) and record the results in the PR. Then fix the
last spec-model leftovers: the `Purpose:` labels, the "no deployment spec" clause, and the
system-organization note in `configuration.md`. Every file touched is owned by `architect`,
so the plan has no specialist split.

## Context

- Pre-move commit: `22b232c` (parent of the move commit `8c946f4`, #186). The `architecture`
  merge is `c556883` (#187) and the `testing` fold is `c7c945d` (#188).
- Pre-check on `main` (`ffa4a5f`):
  - `git diff -M --name-status 22b232c 8c946f4 -- docs/agents` shows 97 renames (83 at
    100% similarity, 14 at 91–99%) and 9 modified files.
  - No `docs/agents/specs/` reference remains outside `issues/`/`plans/`, and no
    `](…specs/<area>` doc link.
  - A relative-link and anchor check finds 0 broken links.
  - Count changes, pre-move `docs/agents/specs/` + `architecture.md` + `contributing*` vs today's
    `docs/agents/` (excluding `issues/`, `plans/`):
    - `CREATE TABLE` 6=6, `DELETE /` 18=18, `PENDING` 4=4, `ERROR_` 0=0;
    - `POST /` 127→119, `GET /` 47→45, `PATCH /` 19→17 (all in `api/endpoints/`, most likely
      #189's wording cleanup);
    - `VALIDATION_FAILED` 68→66;
    - every environment variable name keeps or raises its count (the post side also includes
      the deployment and guide docs).
- The user decided:
  - turn the `Purpose:` lines into plain opening sentences;
  - drop only "; there is no deployment spec" from `deployment.md`;
  - add a Pending-decision note to `configuration.md`;
  - commit no audit script; the results go in the PR body.

## Implementation Steps

### Step 1 — Audit and record

This step reads only; it changes no docs unless it finds a real loss.

1. **Renames.** Run `git diff -M --name-status 22b232c 8c946f4 -- docs/agents`. Check that every
   `docs/agents/specs/<path>` maps to `docs/agents/<path>`. The exceptions are `specs/architecture*`,
   merged by `c556883`, and `specs/testing*`, folded by `c7c945d`; confirm their content through
   those commits' diffs. Summarize the mapping in the PR, with counts and the non-100% renames.
2. **Contracts.** For `CREATE TABLE`, `POST /`, `GET /`, `PATCH /`, `DELETE /`, `PENDING`, `ERROR_`,
   `Pending decision`, the error codes (at least `VALIDATION_FAILED`) and every environment
   variable name, compare per-file `git grep -c` counts between the two sides above.
   - For each file whose count drops, find the removed mention in
     `git log -p 22b232c..HEAD -- <file>` (and its old `specs/` path).
   - Confirm the endpoint or code is still documented, normally in its own `##` section.
   - Record one line per difference in the PR: file, pattern, the commit that removed it, and why.
   - If something was really lost, restore it from `22b232c`, verbatim at its mapped path.
3. **References.** Run `git grep -n "docs/agents/specs/" -- ':!docs/agents/issues' ':!docs/agents/plans'`
   and a grep for `](` links containing `specs/` in the same docs set. Both must be empty;
   Jasmine paths such as `frontend/specs/` in prose are fine.
4. **Tree.** `docs/agents/specs/` must be absent, `docs/agents/specs.md` must index the docs, and
   `architecture.md` must state no subordination to a spec.

### Step 2 — Fix leftovers and re-check links

1. **`Purpose:` lines.** In each of the 11 files below, line 3 starts with `Purpose: `. Remove that
   prefix and capitalize the first word, keeping the rest of the sentence unchanged, including
   its continuation lines. Re-wrap only if a line exceeds the file's usual width.
   - `docs/agents/architecture/backend.md`
   - `docs/agents/architecture/configuration.md`
   - `docs/agents/architecture/database.md`
   - `docs/agents/architecture/dependencies.md`
   - `docs/agents/architecture/repository-layout.md`
   - `docs/agents/deployment.md`
   - `docs/agents/deployment/known-gaps.md`
   - `docs/agents/deployment/operations.md`
   - `docs/agents/deployment/pipeline.md`
   - `docs/agents/deployment/server.md`
   - `docs/agents/deployment/variables.md`

   The `deployment/*` files open with "list…/runbooks…/describe…", which become "List…/Runbooks…/Describe…".
2. **`docs/agents/deployment.md`.** Replace "These docs are the deployment reference; there is no
   deployment spec." with "These docs are the deployment reference." Keep the issues/plans
   sentence and the `contributing.md#deployment-changes` link.
3. **`docs/agents/architecture/configuration.md`.** Line 203, the paragraph starting "There is no
   mail-enabled flag", stays. Append one sentence after it: "How the system organization is
   marked is still a **Pending decision** (see
   [recovery.md](../api/endpoints/auth/recovery.md#pending-decisions)); until it is decided
   there is no system organization and no system email is sent."
4. **Final check.** Re-run the Step 1 reference greps, plus
   `git grep -n "^Purpose:" -- docs/agents ':!docs/agents/issues' ':!docs/agents/plans'` and
   `git grep -n "no deployment spec" -- docs/agents ':!docs/agents/issues' ':!docs/agents/plans'`.
   All must be empty.
   - Run a relative-link and anchor check over `docs/agents/` (excluding `issues/`, `plans/`),
     `README.md`, `AGENTS.md` and `.claude/agents/`. It must report 0 broken links. Use an ad-hoc
     script outside the repo, GitHub-style anchor slugs, and skip fenced code blocks.
   - Paste the outputs into the PR body.

Commit: `docs(agents): final audit of the spec fold and leftover cleanup (#194)`, plus a separate
`fix(docs): …` commit only if Step 1 restored lost content.

## Files to Change

- `docs/agents/architecture/backend.md`, `configuration.md`, `database.md`, `dependencies.md`,
  `repository-layout.md`: drop the `Purpose:` label.
- `docs/agents/architecture/configuration.md`: add the Pending-decision note after line 203.
- `docs/agents/deployment.md`: drop the `Purpose:` label and "; there is no deployment spec".
- `docs/agents/deployment/known-gaps.md`, `operations.md`, `pipeline.md`, `server.md`,
  `variables.md`: drop the `Purpose:` label.
- Any file under `docs/agents/` where Step 1 finds a real contract loss: restore it from `22b232c`.

## Notes

- The PR body carries:
  - the audit: rename summary, contract-difference table, grep and link-check outputs;
  - "Closes #194" and "Part of #185".
- Close Epic #185 after the merge.
- Do not commit an audit script.
- Never blind-replace `specs/`, because Jasmine paths contain it.
- Out of scope: `docs/agents/issues/`, `docs/agents/plans/`, the frontend `specs/` folders,
  `.claude/state/*.json`, and auditing the code against the docs.
- Anything larger than a small fix becomes its own issue (spin it off and mention it in the PR).
