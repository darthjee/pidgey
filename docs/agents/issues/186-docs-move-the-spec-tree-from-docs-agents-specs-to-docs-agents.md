# Issue: Docs: move the spec tree from docs/agents/specs/ to docs/agents/

## Description

Part of Epic #185. `docs/agents/specs/` (about 80 `.md` files, ~9.9k lines, 1,185 relative links, 464 of them between areas) must move up one level into `docs/agents/`. Because of the cross-area links, the whole tree moves **atomically** in this one sub-issue; every later sub-issue builds on the paths fixed here.

## Problem

The spec tree has 1,185 relative links (464 between areas), plus references from `deployment/*.md`, `specs.md`, `architecture.md`, `folder-structure.md`, `contributing.md`, `README.md`, `AGENTS.md`, `.claude/agents/*.md` and `docker-compose.yml`. Moving areas one at a time would leave broken links at every step, and mixing the move with edits would hide it from git rename detection, so the zero-loss guarantee could not be checked.

## Expected Behavior

- [ ] Every file previously under `docs/agents/specs/` (except `specs/architecture.md`) exists at its mapped path under `docs/agents/`; `git log --follow`/rename detection shows commit 1 as pure renames.
- [ ] `docs/agents/specs/` contains only `architecture.md`.
- [ ] No broken relative link or anchor in `docs/agents/`, `README.md`, `AGENTS.md`, `.claude/agents/` (checked with a link-check script or equivalent, listed in the PR).
- [ ] No path reference to a moved spec file remains (`git grep -n "docs/agents/specs/"` and `../specs/` only hit `specs/architecture.md` and `issues/`/`plans/`).
- [ ] No content change other than link targets.
- [ ] Frontend Jasmine `specs/` paths untouched.

## Solution

### What to do

1. **Commit 1 — pure move (no content edits):** `git mv` every area hub and folder up one level: `overview`, `persistence`, `request-handler`, `docker`, `organizations`, `email`, `api`, `frontend`, the `architecture/` **folder**, and `testing.md` + `testing/` (temporarily to `docs/agents/testing.md` + `docs/agents/testing/`; a later sub-issue (#188) folds them into `contributing`).
   - **Do not move `specs/architecture.md`**: `docs/agents/architecture.md` already exists. The spec hub stays in `docs/agents/specs/` until the `architecture` merge sub-issue (#187). Links from moved files to `../architecture.md` will resolve to the existing summary meanwhile, which is acceptable.
   - A move-only commit is required so git rename detection proves zero loss (some files are ~9 lines; a link edit in the same commit can drop them below the similarity threshold).
2. **Commit 2 — link fixes inside the moved files:**
   - The 26 links that escape the tree gain one level less (`../../deployment/pipeline.md` → `../deployment/pipeline.md`, `../../../deployment.md` → `../../deployment.md`, etc.).
   - Links from moved files to `architecture.md` (the spec hub) now land on `docs/agents/architecture.md`; links into `architecture/*` keep working unchanged.
   - The hub left behind, `docs/agents/specs/architecture.md`, has 5 links to its detail files: `architecture/<file>.md` → `../architecture/<file>.md`.
   - Fix the two already-broken anchors `#bin-check-config` in `architecture/dependencies.md` and `architecture/configuration.md` → `#bincheck-config` (the slug of the `` `bin/check-config` `` heading).
   - Links to the directory `../api/endpoints/` stay valid; leave them.
3. **Commit 3 — path-only retargeting outside the tree** (no wording changes; those belong to later sub-issues):
   - `docs/agents/specs.md` index links (`specs/<area>.md` → `<area>.md`, except `specs/architecture.md` which still exists).
   - `docs/agents/architecture.md`, `folder-structure.md`, `contributing.md` (e.g. `specs/testing/definition-of-done.md` → `testing/definition-of-done.md`).
   - `docs/agents/deployment/*.md` (`known-gaps.md`, `operations.md`, `pipeline.md`, `server.md`): ~20 links `../specs/<area>/...` → `../<area>/...`, anchors preserved.
   - `README.md` (3 references incl. `#first-run`), `AGENTS.md`, `.claude/agents/*.md` (~25 path references), `docker-compose.yml` (one comment).

### Shared rules (from Epic #185)

- `docs/agents/specs/` is being folded into `docs/agents/`, keeping the **hub + detail-files** shape: `specs/<area>.md` → `docs/agents/<area>.md`, `specs/<area>/**` → `docs/agents/<area>/**` (same relative paths, one level up). Exceptions: `architecture` (merged with the existing `docs/agents/architecture.md`) and `testing` (folded into `contributing.md` + `contributing/testing.md`).
- **Zero contract loss:** endpoints (method, path, payload, response), tables/columns/types/indexes, class and public method signatures, environment variables, error codes and failure taxonomy are preserved intact. Do not rewrite prose for style.
- **Every relative link must still resolve** when this sub-issue merges.
- **Never retarget with a blind global replace of `specs/`:** frontend Jasmine paths (`specs/support/fixtures/`, `specs/assets/js/`, `specs/**/*[sS]pec.js`, `frontend/specs/...`) contain the same string and must not change.
- Out of scope everywhere: `docs/agents/issues/`, `docs/agents/plans/`, the frontend's `specs/` folders, `.claude/state/*.json`, and auditing whether the code matches the docs.

### Edge cases

Verified by simulating the commit-1 move on a copy of the tree and running a relative-link + anchor check.

#### Link inventory after the pure move (what commits 2 and 3 must fix)

| Where | Broken links | Fix |
|---|---|---|
| Moved files (`docs/agents/<area>/**`) | 26 | escaping links lose one `../` (`../../deployment/…` → `../deployment/…`) |
| `docs/agents/specs/architecture.md` (the hub that stays) | 5 | `architecture/<file>.md` → `../architecture/<file>.md` (its detail folder moved up while it stayed) |
| `docs/agents/deployment/*.md` | 21 | `../specs/<area>/…` → `../<area>/…` |
| `docs/agents/specs.md` | 9 | `specs/<area>.md` → `<area>.md` (including `specs/testing.md` → `testing.md`); `specs/architecture.md` stays as is |
| `docs/agents/architecture.md` | 5 | `specs/<area>/…` → `<area>/…`; `specs/overview/components.md` → `overview/components.md` |
| `docs/agents/folder-structure.md` | 3 | `specs/<area>/…` → `<area>/…` |
| `docs/agents/contributing.md` | 1 | `specs/testing/definition-of-done.md` → `testing/definition-of-done.md` |
| `README.md` | 3 | `docs/agents/specs/<area>/…` → `docs/agents/<area>/…` (anchor `#first-run` kept) |

About 73 links in total. Commit 2 covers the first two rows (inside `docs/agents/specs/` and the moved tree); commit 3 covers the rest. Every anchor in these links still resolves after the retarget (anchors live in the target files, which are unchanged).

#### Pre-existing broken anchors

The only anchor failures after the move already exist today: `#bin-check-config` in `architecture/dependencies.md` and `architecture/configuration.md`. The heading `` `bin/check-config` `` slugs to `#bincheck-config`. Fix both in commit 2.

#### Backticked paths (not links): `AGENTS.md`, `.claude/agents/*.md`, `docker-compose.yml`

No markdown links into the tree exist in these files; references are backticked paths in prose. Classify each:

- **Names a concrete file that moved** → retarget the path (path-only edit). E.g. `docs/agents/specs/request-handler/front-controller.md`, `docs/agents/specs/frontend/build.md`, `docs/agents/specs/architecture/dependencies.md` (`backend.md`, `security.md`), `docs/agents/specs/email/failures.md`, `docs/agents/specs/email.md` (`email.md`), `docs/agents/specs/docker.md` (`infra.md`), `docs/agents/specs/frontend.md` / `frontend/*.md` (`frontend.md`), and the `docker-compose.yml` comment (`docs/agents/specs/email/testing.md`).
- **Describes a rule or pattern** → leave untouched (wording change, deferred to the entry-points / agent-definitions sub-issues (#192 / #193)). E.g. `AGENTS.md`'s granularity rule (`docs/agents/specs/<name>.md`, `docs/agents/specs/<name>/*.md`), `product-owner.md`'s `specs/<name>.md` rule.
- **Names the directory `docs/agents/specs/` as an ownership target** ("Do NOT touch `docs/agents/specs/` (`product-owner`)" in `backend`, `frontend`, `infra`; the ownership table in `architect.md`; `product-owner.md`'s own list) → leave untouched. The directory still exists after this sub-issue (it holds `architecture.md`), and ownership is rewritten by the agent-definitions sub-issue (#193).
- `docs/agents/specs.md` references → unchanged (the file keeps its name and place).

#### Temporary `testing` location

`specs/testing.md` + `specs/testing/` move to `docs/agents/testing.md` + `docs/agents/testing/` like any other area (no collision). They are folded into `contributing` by a later sub-issue (#188); here only the links pointing at them are retargeted.

#### Other cases

- Directory links (`../api/endpoints/`, 4 in `request-handler/*`) stay valid after the move; leave them.
- Frontend Jasmine strings (`specs/support/fixtures/`, `specs/assets/js/`, `specs/**/*[sS]pec.js`, `frontend/specs/...`) are unrelated and must not change — retarget by explicit path, never by a global replace of `specs/`.
- `docs/agents/issues/` and `docs/agents/plans/` contain no links into the tree; nothing to do.

### Notes

- **No freeze window needed:** the repository has a single maintainer, so no concurrent edits to `docs/agents/specs/` are expected. No pre-check, label or announcement is required.
- **Ownership: a single PR, every commit by `architect`.** Not dispatched across agents:
  - The commits must land in strict order (pure move → link fixes → external retargeting); parallel agents cannot guarantee that, and an edit before the move commit breaks rename detection.
  - The PR makes no content decisions — only renames and link targets — so the per-area ownership rules protect nothing here; content decisions happen in the later sub-issues, which keep their owners.
  - `architect` is the cross-cutting agent and already owns most files outside the tree (`AGENTS.md`, `docs/agents/*`, `.claude/agents/*`); the one-line `docker-compose.yml` comment does not justify involving `infra`.
  - The PR description states the exception explicitly: `product-owner` (`docs/agents/specs/**`) and `infra` (`docker-compose.yml`) own files touched with path-only edits.
  - Strictly path-only: anything that would need a wording change (e.g. the spec granularity rule text in `AGENTS.md`, which names `docs/agents/specs/<name>.md` as a rule, not a link) is left untouched and deferred to the entry-points / agent-definitions sub-issues (#192 / #193).
- **No dedicated testing strategy:** this is a docs-only move with no code or CI impact. Verification is just the Acceptance Criteria above (rename detection on commit 1, an ad-hoc link check, `git grep`); no script is committed and no CI job is added. The full no-loss audit belongs to the final-audit sub-issue (#194).

## Benefits

- Every later sub-issue of #185 (#187–#194) works on final paths and never moves files.
- Zero loss is provable: commit 1 is pure renames.
- No broken link at any point; the repo stays navigable for agents throughout.
