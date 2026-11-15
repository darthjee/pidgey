# Plan: Docs: move the spec tree from docs/agents/specs/ to docs/agents/

Issue: [186-docs-move-the-spec-tree-from-docs-agents-specs-to-docs-agents.md](../../issues/186-docs-move-the-spec-tree-from-docs-agents-specs-to-docs-agents.md)

## Overview

Move every spec area from `docs/agents/specs/` up one level into `docs/agents/` in a single PR, implemented by `architect` alone, as three ordered commits: a pure `git mv` (so git rename detection proves zero loss), link fixes inside the moved tree, then path-only retargeting of references outside it. No wording, rule or ownership text changes — those belong to #187–#193.

## Context

- Part of Epic #185. The tree has ~80 `.md` files and 1,185 relative links (464 between areas), so it moves atomically.
- `specs/architecture.md` is **not** moved (collides with the existing `docs/agents/architecture.md`; merged later by #187). `specs/testing*` moves temporarily to `docs/agents/testing*` (folded into `contributing` by #188).
- A simulation of the move on a copy of the tree found ~73 broken links to fix (26 escaping the moved tree, 5 in the hub left behind, the rest outside the tree), plus 2 pre-existing broken anchors (`#bin-check-config`).
- Single maintainer: no freeze window. No dedicated testing strategy beyond the acceptance criteria.
- Never use a global replace of `specs/`: frontend Jasmine paths (`specs/support/…`, `specs/assets/js/…`, `specs/**/*[sS]pec.js`, `frontend/specs/…`, and the code example `specs/client/ApiClient_spec.js` in `contributing.md`) must stay untouched.

## Steps

- [01 — Pure move](plan/01-pure-move.md)
- [02 — Fix links inside the moved tree](plan/02-fix-links-inside-tree.md)
- [03 — Retarget references outside the tree](plan/03-retarget-outside-references.md)

## CI Checks

No CI job covers `docs/`, `AGENTS.md`, `README.md` or `.claude/agents/`. The `docker-compose.yml` edit is a comment only. Run before opening the PR:

- An ad-hoc relative-link + anchor check over `docs/agents/` (excluding `issues/`, `plans/`), `README.md`, `AGENTS.md`, `.claude/agents/` — expect zero broken links (directory links such as `../api/endpoints/` count as valid).
- `git diff -M --stat origin/main..HEAD~2` (commit 1 only) — every entry is a 100% rename.
- `git grep -n "docs/agents/specs/" -- ':!docs/agents/issues' ':!docs/agents/plans'` — only hits that name `specs/architecture.md`, the directory `docs/agents/specs/` as an ownership target, or the granularity-rule patterns (`specs/<name>…`).

## Notes

- Commit order is mandatory; do not squash on merge in a way that loses commit 1 as a pure-rename commit (prefer a merge commit or rebase-merge).
- The PR description must state that `product-owner` (`docs/agents/specs/**`) and `infra` (`docker-compose.yml`) own files touched with path-only edits, and include the commands/script used for the move and the link check.
- Leave untouched (deferred): the "spec wins" banner and its `specs/architecture.md` link in `architecture.md` (#187), `AGENTS.md` granularity rule text (#192), "Do NOT touch `docs/agents/specs/`" ownership lines and `product-owner.md` rules (#193).
