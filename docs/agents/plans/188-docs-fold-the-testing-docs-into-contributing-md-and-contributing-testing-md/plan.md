# Plan: Docs: fold the testing docs into contributing.md and contributing/testing.md

Issue: [188-docs-fold-the-testing-docs-into-contributing-md-and-contributing-testing-md.md](../../issues/188-docs-fold-the-testing-docs-into-contributing-md-and-contributing-testing-md.md)

## Overview

Fold the leftover testing docs (`docs/agents/testing.md` + `docs/agents/testing/`) into the
contributing docs: backend and frontend testing move to a new `docs/agents/contributing/testing.md`,
the old Definition of Done merges into the one in `contributing.md`, the old files are deleted and
every inbound link is retargeted. Docs only; owned by `architect`, single PR.

## Context

- Part of Epic #185; depends on #186 (merged). Parallel with #187 and the area cleanups.
- `docs/agents/contributing/` does not exist yet. It sits at the same depth as `docs/agents/testing/`,
  so relative links inside the moved text (`../email/...`, `../architecture/...`,
  `../deployment/...`, `../frontend/testing.md`) resolve unchanged.
- Zero contract loss; never retarget with a blind replace of `specs/` — the Jasmine paths
  (`specs/assets/js/`, `specs/support/`, `specs/support/fixtures/`) in `testing/frontend.md` must
  stay byte-identical.
- Out of scope: the `contributing.md` intro ("specs … are the source of truth", #192), `.claude/agents/`
  (#193), `docs/agents/issues/`, `docs/agents/plans/`.

## Implementation Steps

### Step 1 — Create contributing/testing.md and merge the Definition of Done

1. Create `docs/agents/contributing/testing.md`:
   - `# Testing` title, then a one-line intro pointing back to [contributing.md](../contributing.md)
     and to the area testing docs that stay with their areas
     ([persistence/testing.md](../persistence/testing.md), [frontend/testing.md](../frontend/testing.md),
     [email/testing.md](../email/testing.md)) — links only, no duplicated content.
   - `## Backend Testing`: the full body of `testing/backend.md` without its `# Backend Testing` title
     and its `Purpose:` line; `## Email test support` → `### Email test support`,
     `## Configuration loading` → `### Configuration loading`. All other text unchanged.
   - `## Frontend Testing`: the full body of `testing/frontend.md` without its title and `Purpose:` line;
     the final pointer stays `[frontend/testing.md](../frontend/testing.md)`. Jasmine paths unchanged.
   - Use `git mv docs/agents/testing/backend.md docs/agents/contributing/testing.md` first so history
     follows the larger file, then edit it into the shape above.
2. In `docs/agents/contributing.md` "Definition of Done for PRs":
   - Intro: drop the "(see also [testing/definition-of-done.md](testing/definition-of-done.md))" pointer.
   - Reword "Relevant specs were read, and updated first if the change alters a decision." to
     "Relevant docs in `docs/agents/` were read, and updated first if the change alters a decision."
   - Add, after that item: "New behavior is covered by tests (unit, and integration where DB/SMTP is involved)."
   - Keep "All tests are passing … `make tests` is green." and add, after the linting item: "CI is green."
   - Add a link to the new doc: "Test suites, tooling and commands: see [contributing/testing.md](contributing/testing.md)."
     (one sentence under the DoD heading, before the list).
3. Delete `docs/agents/testing.md`, `docs/agents/testing/frontend.md` and
   `docs/agents/testing/definition-of-done.md` (`git rm`), so `docs/agents/testing/` is gone.
4. Commit: `docs: fold testing docs into contributing (#188)`.

### Step 2 — Retarget inbound links

Edit each link individually (no global replace):

| File | Old target | New target |
| --- | --- | --- |
| `docs/agents/docker/makefile.md:78` | `../testing.md` | `../contributing/testing.md` |
| `docs/agents/frontend/testing.md:76` | `../testing.md` | `../contributing/testing.md` |
| `docs/agents/architecture/repository-layout.md:24` | `../testing.md` | `../contributing/testing.md` |
| `docs/agents/email/testing.md:232` | `../testing.md` | `../contributing/testing.md` |
| `docs/agents/architecture/backend.md:10` | `../testing/backend.md` | `../contributing/testing.md#backend-testing` |
| `docs/agents/architecture/database.md:26` | `../testing/backend.md` | `../contributing/testing.md#backend-testing` |
| `docs/agents/persistence/testing.md:6` | `../testing/backend.md` | `../contributing/testing.md#backend-testing` |
| `docs/agents/email/testing.md:112` | `../testing/backend.md#email-test-support` | `../contributing/testing.md#email-test-support` |
| `docs/agents/frontend/testing.md:43` | `../testing/definition-of-done.md` | `../contributing.md#definition-of-done-for-prs` |
| `docs/agents/specs.md:27` | `testing.md` | `contributing/testing.md` (path only) |

Update link text where it names the old file (e.g. `[testing/backend.md](...)` →
`[contributing/testing.md](...)`); keep surrounding prose.

Then re-grep to confirm nothing is left:

```bash
grep -rnE '\]\((\.\./)*testing(\.md|/)' docs/agents README.md AGENTS.md .claude/agents \
  --include=*.md | grep -v 'docs/agents/issues/\|docs/agents/plans/'
```

Commit: `docs: retarget links to contributing/testing.md (#188)`.

## Files to Change

- `docs/agents/contributing/testing.md` — new (from `testing/backend.md` + `testing/frontend.md`).
- `docs/agents/contributing.md` — merged Definition of Done, link to `contributing/testing.md`.
- `docs/agents/testing.md`, `docs/agents/testing/` — deleted.
- `docs/agents/docker/makefile.md`, `docs/agents/frontend/testing.md`,
  `docs/agents/architecture/repository-layout.md`, `docs/agents/architecture/backend.md`,
  `docs/agents/architecture/database.md`, `docs/agents/persistence/testing.md`,
  `docs/agents/email/testing.md`, `docs/agents/specs.md` — link retargets.

## Verification

- `test ! -e docs/agents/testing.md && test ! -e docs/agents/testing && test -f docs/agents/contributing/testing.md`.
- The re-grep above returns nothing.
- `grep -n 'specs/assets/js/\|specs/support/' docs/agents/contributing/testing.md` shows the Jasmine
  paths exactly as in the old `testing/frontend.md`.
- Every relative link (and `#anchor`) under `docs/agents/` (excluding `issues/`, `plans/`), `README.md`
  and `.claude/agents/` resolves — check with a small local script (no CI job checks doc links).
- Diff the moved backend/frontend text against the deleted originals: only heading levels, the
  removed titles/`Purpose:` lines change.

## Notes

- No CI job covers `docs/`; there are no CI commands to run.
- Pre-existing links that already target area testing docs (`email/testing.md`, `frontend/testing.md`,
  `persistence/testing.md`) are unaffected.
