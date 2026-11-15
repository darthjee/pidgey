# Issue: Docs: final audit of the spec fold (no loss, no broken links)

## Description

Part of Epic #185, last sub-issue. Sub-issues #186–#193 folded `docs/agents/specs/` into `docs/agents/`, cleaned up the wording and rewired the entry points and agent definitions. This issue verifies that the fold lost no contract and left no broken reference, fixes the last leftovers found along the way, and closes the Epic.

## Problem

A pre-check on `main` (after #193, `ffa4a5f`) shows the fold is mostly clean, with these open items:

- **Contract counts differ** between the pre-move tree (`22b232c`, parent of the move commit `8c946f4`) and today's docs, all in `docs/agents/api/endpoints/`:
  - `POST /`: 127 → 119 (`admin-users/edit.md`, `admin-users/recovery.md`, `admin-users/review.md`, `auth/recovery.md`, `sending.md`);
  - `GET /`: 47 → 45 (`operational.md`, `pidgey-admin/organization.md`);
  - `PATCH /`: 19 → 17 (`auth/account.md`, `pidgey-admin/organization.md`).
  - `CREATE TABLE`, `DELETE /` and `PENDING` match; `ERROR_` is 0 on both sides. The drops are expected to come from #189's wording cleanup (repeated mentions of an endpoint), but each one must be confirmed and explained.
- **Leftover `Purpose:` lines** (a convention #193 dropped) open 11 docs: `architecture/backend.md`, `architecture/configuration.md`, `architecture/database.md`, `architecture/dependencies.md`, `architecture/repository-layout.md`, `deployment.md`, and `deployment/known-gaps.md`, `operations.md`, `pipeline.md`, `server.md`, `variables.md`.
- **`deployment.md`** still says "These docs are the deployment reference; there is no deployment spec."
- **`architecture/configuration.md:203`** says system emails are "sent through the system organization's active SMTP connection". It doesn't say that how the system organization is marked is still a **Pending decision** (`api/endpoints/auth/recovery.md#pending-decisions`), so no system organization exists yet and no system email is sent.

Already clean: no `docs/agents/specs/` reference outside `issues/`/`plans/`, no `specs/<area>` doc links, `docs/agents/specs/` is gone, `specs.md` is the hub, and a relative-link + anchor check over `docs/agents/`, `README.md`, `AGENTS.md` and `.claude/agents/` finds 0 broken links.

## Expected Behavior

- Every former spec file has a counterpart at its mapped path, confirmed by git rename detection.
- Every contract-count difference between the pre-move tree and today's docs is explained in the PR (a dropped duplicate mention, not a lost endpoint); a real loss is restored.
- No doc opens with a `Purpose:` label, and no doc says there is "no deployment spec".
- `configuration.md` states that the system organization is still a Pending decision.
- Zero `docs/agents/specs/` references outside `issues/`/`plans/`, zero broken relative links or anchors, `docs/agents/specs/` absent and `docs/agents/specs.md` present as the hub.
- Epic #185 can be closed.

## Solution

1. **Rename audit:** run `git diff -M --stat 22b232c 8c946f4 -- docs/agents` (plus the merge commits of #187 and #188 for `architecture` and `testing`) and confirm that every file of the pre-move `docs/agents/specs/` tree maps to its path under `docs/agents/`. List the mapping summary in the PR.
2. **Contract audit:** for `CREATE TABLE`, `POST /`, `GET /`, `PATCH /`, `DELETE /`, `PENDING`, `ERROR_`, `Pending decision` and every environment variable name, compare counts in `22b232c:docs/agents/specs/` (plus `architecture.md` and `contributing*`) against today's `docs/agents/` (excluding `issues/` and `plans/`), per file. For each difference listed under Problem, show from the diff which mention was removed and that the endpoint is still documented. Restore anything actually lost.
3. **`Purpose:` lines:** in the 11 docs listed under Problem, drop the `Purpose:` label and keep the sentence as the doc's opening line (capitalized, otherwise unchanged). No other rewording.
4. **`deployment.md`:** drop "; there is no deployment spec" so the sentence reads "These docs are the deployment reference."; keep the issues/plans sentence and the contributing link.
5. **`architecture/configuration.md:203`:** keep the sentence and add that how the system organization is marked is still a **Pending decision** ([recovery.md](../api/endpoints/auth/recovery.md#pending-decisions)); until it is decided there is no system organization and no system email is sent.
6. **Final checks:** re-run the reference grep (`git grep -n "docs/agents/specs/"` outside `issues/`/`plans/`, and no `](…specs/<area>` doc links) and a relative-link + anchor check over `docs/agents/` (excluding `issues/`/`plans/`), `README.md`, `AGENTS.md` and `.claude/agents/`; paste the results into the PR. No audit script is committed.
7. **Close Epic #185** once the PR merges.

Anything larger than a small fix becomes its own issue.

### Ownership

- `docs/agents/architecture/` and `docs/agents/deployment*`: `architect` (per #193).

### Shared rules (from Epic #185)

- **Zero contract loss:** endpoints, tables/columns/types/indexes, class and public method signatures, environment variables, error codes and failure taxonomy are preserved. Do not rewrite prose for style.
- **Every relative link must still resolve.**
- **Never retarget with a blind global replace of `specs/`:** the frontend Jasmine paths contain the same string.
- **Out of scope:** `docs/agents/issues/`, `docs/agents/plans/`, the frontend `specs/` folders, `.claude/state/*.json`, and auditing whether the code matches the docs.

### Acceptance Criteria

- [ ] Rename detection covers every former spec file.
- [ ] Contract audit counts match, or every difference is explained in the PR.
- [ ] No `Purpose:` label and no "no deployment spec" wording remains in `docs/agents/` (outside `issues/`/`plans/`).
- [ ] `configuration.md` marks the system organization as a Pending decision, with a resolving link.
- [ ] No reference to `docs/agents/specs/` outside `issues/`/`plans/`.
- [ ] Zero broken relative links/anchors.
- [ ] `docs/agents/specs/` absent, `docs/agents/specs.md` present as hub.

## Benefits

- Proof that the fold kept every contract, recorded in the PR.
- The docs no longer carry spec-model leftovers (`Purpose:`, "no deployment spec").
- The configuration reference no longer implies that system emails are sent today.
- Epic #185 closes with a verified, link-clean docs tree.
