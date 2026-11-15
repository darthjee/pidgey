# Issue: Docs: fold the testing docs into contributing.md and contributing/testing.md

## Description

Part of Epic #185. After the move sub-issue (#186), the former `specs/testing*` files sit temporarily at `docs/agents/testing.md` (an 11-line index) and `docs/agents/testing/` (`backend.md` 155 lines, `frontend.md` 13 lines, `definition-of-done.md` 9 lines). Decision from the Epic: there is no top-level `docs/agents/testing.md`. This sub-issue folds those files into the contributing docs, following the `deployment.md` + `deployment/*.md` precedent.

## Problem

- Testing guidance lives in a leftover spec-shaped index (`testing.md`) plus three detail files, separate from `contributing.md`, where contributors look for how to work on the project.
- The Definition of Done exists twice: `testing/definition-of-done.md` and the "Definition of Done for PRs" in `contributing.md`, and they have drifted — two old items ("New behavior is covered by tests (unit, and integration where DB/SMTP is involved)" and "CI is green") have no counterpart in `contributing.md`.
- 11 relative links across the docs point at `testing.md`, `testing/backend.md` or `testing/definition-of-done.md`.

## Expected Behavior

- `docs/agents/contributing/testing.md` holds the backend and frontend testing docs, content intact.
- `contributing.md` holds the single Definition of Done, containing every item of the old one, and links to `contributing/testing.md`.
- `docs/agents/testing.md` and `docs/agents/testing/` no longer exist, and no link points at them.
- Every relative link resolves; frontend Jasmine paths in the testing text are unchanged.

## Solution

### Changes

- **Create `docs/agents/contributing/testing.md`** with two sections keeping the original titles:
  - `## Backend Testing` — the content of `testing/backend.md`; its subsections `Email test support` and `Configuration loading` become `###` (anchors unchanged).
  - `## Frontend Testing` — the content of `testing/frontend.md`; its pointer to the detailed frontend doc becomes `../frontend/testing.md`.
  - The two `Purpose:` lines are dropped. Relative links inside the moved text keep their `../` prefix (same folder depth).
  - Testing docs of other areas stay with their areas (`persistence/testing.md`, `frontend/testing.md`, `email/testing.md`); `contributing/testing.md` links to them and never duplicates them.
- **Merge the Definition of Done** into the existing "Definition of Done for PRs" in `contributing.md` (no separate copy). No item may be lost:
  - Add "New behavior is covered by tests (unit, and integration where DB/SMTP is involved)".
  - Add "CI is green".
  - Reword "Relevant specs were read" to reference the docs in `docs/agents/`.
  - Remove the "(see also [testing/definition-of-done.md](...))" pointer from the DoD intro.
- **Link `contributing.md` to `contributing/testing.md`.**
- **Delete** `docs/agents/testing.md` and `docs/agents/testing/`.
- **Retarget every inbound link** (found today):
  - `testing.md` → `contributing/testing.md`: `docker/makefile.md:78`, `frontend/testing.md:76`, `architecture/repository-layout.md:24`, `email/testing.md:232`.
  - `testing/backend.md` → `contributing/testing.md#backend-testing`: `architecture/backend.md:10`, `architecture/database.md:26`, `persistence/testing.md:6`.
  - `testing/backend.md#email-test-support` (`email/testing.md:112`) → `contributing/testing.md#email-test-support`.
  - `testing/definition-of-done.md` → `contributing.md#definition-of-done-for-prs`: `frontend/testing.md:43`.
  - `specs.md:27`: path only, `[testing](testing.md)` → `[testing](contributing/testing.md)`; rewriting `specs.md` stays with the entry-points sub-issue (#192).

### Out of scope

- The `contributing.md` intro ("specs … are the source of truth") — handled by #192.
- `.claude/agents/` has no testing link; its `specs/` reference belongs to #193.

### Shared rules (from Epic #185)

- `docs/agents/specs/` is folded into `docs/agents/`, keeping the **hub + detail-files** shape; exceptions: `architecture` (merged with `docs/agents/architecture.md`) and `testing` (this sub-issue).
- **Zero contract loss:** endpoints, tables/columns/types/indexes, class and public method signatures, environment variables, error codes and failure taxonomy are preserved intact. Do not rewrite prose for style.
- **Every relative link must still resolve** when this sub-issue merges.
- **Never retarget with a blind global replace of `specs/`:** frontend Jasmine paths (`specs/support/fixtures/`, `specs/assets/js/`, `specs/**/*[sS]pec.js`, `frontend/specs/...`) contain the same string and must not change.
- Out of scope everywhere: `docs/agents/issues/`, `docs/agents/plans/`, the frontend's `specs/` folders, `.claude/state/*.json`, and auditing whether the code matches the docs.

### Testing and ownership

- Verify every relative link under `docs/agents/` (excluding `issues/`, `plans/`), `README.md` and `.claude/agents/` resolves, and that the Jasmine paths in the testing text are unchanged.
- `architect` owns all files; single PR. Docs only — backward compatibility, performance and security are not applicable.
- Depends on #186 (merged). Parallel with #187 and the area cleanups.

### Acceptance Criteria

- [ ] `docs/agents/contributing/testing.md` exists with `Backend Testing` and `Frontend Testing`, content intact.
- [ ] `contributing.md` Definition of Done contains every item of the old spec DoD, including "CI is green" and "New behavior is covered by tests".
- [ ] `docs/agents/testing.md` and `docs/agents/testing/` no longer exist; no link points at them.
- [ ] Frontend Jasmine paths (`specs/assets/js/`, `specs/support/`) inside the testing text are unchanged.
- [ ] All relative links resolve.

## Benefits

- One place for contribution and testing guidance, and a single Definition of Done with no drift.
- Removes one more spec-shaped leftover on the way to deleting the spec tree (Epic #185).
