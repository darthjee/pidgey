# Issue: Docs: wording and Pending-decision cleanup for the api docs

## Description

Part of Epic #185 (fold `docs/agents/specs/` into `docs/agents/`). The move (#186) is merged, so the api docs now live at `docs/agents/api.md` and `docs/agents/api/**`. They still describe themselves as "specs", and their `Pending decision` marks have to be reviewed to see whether any is already settled.

Scope: `docs/agents/api.md` and `docs/agents/api/**` (33 files, about 4,170 lines).

## Problem

- The index files and some prose call these files "specs" or "endpoint specs", which reads as a target-state spec tree that no longer exists.
- The Epic asked for every `Pending decision` mark to be reviewed. A mark that is already decided but still listed as Pending would block agents, because agents never implement a Pending item by guessing.

## Expected Behavior

- The api docs describe themselves as docs, not specs, and contain no target-state markers.
- Every `Pending decision` in scope has been reviewed under the rules below. The PR description records the review result for each item.

## Solution

### Findings from exploration

- **Target-state markers:** none found. Every `target` in scope means "the target user" or "the target connection". The three `future` uses (`client-ip.md:4`, `sending.md:70`, `sessions.md:113`) describe open Pending items, so they stay.
- **`Purpose:` lines:** all 32 files in scope start with a `Purpose:` paragraph (one or more lines) under the title. Six of them call the files "specs".
- **"spec" self-references left after the `Purpose:` removal (to reword "spec(s)" → "doc(s)"):** `api.md:7` (index table), `api/authentication.md:13`, `api/conventions/validation.md:68` and `:77`, `api/conventions/errors.md:99`, `:161` and `:168`, `api/conventions/format.md:38` and `:42`. `api/endpoints/pidgey-admin/smtp-connections.md:14` (`frontend/specs/support/fixtures/…`) is a Jasmine path and is **not** touched.
- **`Pending decision` review:** 24 open items across `pending.md`, `authentication/{api-tokens,access-token,sessions}.md`, `endpoints/auth.md`, `endpoints/auth/{recovery,device-authorization}.md`, `endpoints/admin-users.md`, `endpoints/pidgey-admin/api-tokens.md` and `conventions/validation.md`. No item has repo evidence of being decided:
  - no other doc states a choice for any of them;
  - the closed issues that touch these topics (#27, #28, #30, #31, #128, #129, #132, #139, #166) are the ones that *created* the marks;
  - no item's own file documents the behavior as implemented. For example, the `__Host-access_token` rename is also listed as pending in `request-handler/request-response/request.md:72`; no endpoint has a boolean field, so the validation boolean template is still unneeded; there is no `is_system` column, so the system organization is still undecided.

  So **every Pending item stays unchanged**. The mirrored summaries (`api/authentication.md`, `api/endpoints/pidgey-admin.md`, `api/pending.md`, `organizations/entities.md`) were checked and already match their detail files, so no change is needed.

### Changes

1. Delete the `Purpose:` paragraph (and the blank line after it) from all 32 files in scope. Each one only restates the title, so no contract content is lost.
2. Reword the remaining "spec(s)" self-references listed above to "doc(s)".
3. Make no `Pending decision` edits. The PR description carries the per-item review table (item, file, verdict "open", evidence checked).

### Decisions

- **`Purpose:` lines are dropped, as an Epic-wide convention.** They read as spec-tree boilerplate. #190 and #191 apply the same rule to their areas, and #194 (final audit) removes any left in already-merged areas, such as those kept by #187. The rule is recorded as a comment on #190, #191 and #194.
- **The Pending review is recorded only in the PR description**, as a per-item table (item, file, verdict, evidence checked). Nothing about the review is written into the docs, because no item changes.
- **`architecture/configuration.md:203` is left as is.** It says system emails go through the system organization's active SMTP connection, while `recovery.md` keeps the system organization as Pending. The file is outside this issue's scope, so the mismatch is left for #194 (final audit).

### Out of scope

- `organizations/entities.md` and `request-handler/**` belong to #190. `architecture/configuration.md` was handled by #187.
- Moving or renaming files, and rewriting prose for style.
- From Epic #185: `docs/agents/issues/`, `docs/agents/plans/`, the frontend `specs/` folders, `.claude/state/*.json`, and auditing code against docs.

### Shared rules (from Epic #185)

- **Zero contract loss:** endpoints, payloads, responses, tables, columns, signatures, environment variables, error codes and the failure taxonomy are preserved intact.
- Every relative link still resolves.
- Never retarget with a blind global replace of `specs/`, because frontend Jasmine paths contain the same string.

### Pending-decision review rules

1. An item counts as **decided** only on repo evidence: another doc states the choice, a closed GitHub issue settled it, or the item's own file documents the behavior as implemented. No code audit is done. When it is unclear, the item stays Pending.
2. A decided item moves into the contract text as a recorded decision, and its mirrored summaries are updated with it. Open items stay unchanged.
3. "None." and pointer-only `## Pending decisions` sections stay.
4. No new GitHub issue is opened per open item.

### Testing and ownership

Docs-only. Owner: architect, single PR. Verification:
- grep shows no `^Purpose:` line and no `spec` self-reference left in scope, except the Jasmine path;
- all relative links resolve;
- the diff contains only the `Purpose:` deletions and the word changes.

### Acceptance Criteria

- [ ] No `Purpose:` line is left in `api.md` / `api/**`.
- [ ] No target-state markers or "spec(s)" self-references remain in `api.md` / `api/**`. The Jasmine path in `smtp-connections.md:14` is unchanged.
- [ ] Every `Pending decision` in scope was reviewed, and the PR description lists each item with its verdict and the evidence checked.
- [ ] Mirrored summaries and duplicates are consistent with their detail files.
- [ ] No contract text was lost, and no file was moved or renamed. All relative links still resolve.

### Depends on

#186 (merged). Can run in parallel with #187, #188, #190 and #191 (disjoint files).

## Benefits

- The api docs read as current documentation, not as a target-state spec tree.
- A recorded, evidence-based review confirms that every remaining `Pending decision` is really open, so agents can trust the marks.
