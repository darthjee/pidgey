# Plan: Agents: backend agent references stale static/ frontend path

Issue: [63-agents-backend-agent-references-stale-static-frontend-path.md](../../issues/63-agents-backend-agent-references-stale-static-frontend-path.md)

## Overview

This is a one-line docs fix in `.claude/agents/backend.md`. It replaces the stale `static/` frontend path with `admin/` (served at `/admin/`) and adds pointers to the front-controller and frontend-build specs. Agent definitions are root-level files, so the change belongs to the architect, not to a specialist. The backend agent may only edit files under `source/`.

## Context

- Line 14 of `.claude/agents/backend.md` says the built frontend "lands in `static/`".
- The specs release it under `admin/` in the web root and serve it at `/admin/`:
  - `docs/agents/specs/frontend/build.md`;
  - `docs/agents/specs/architecture/repository-layout.md`, in the `source/public/` row.
- A search of every file in `.claude/agents/` found no other stale `static/` path. `frontend.md` and `infra.md` do not describe the web root, so they stay untouched.

## Implementation Steps

### Step 1 — Replace line 14 of `backend.md`

Replace the line with exactly:

```markdown
- `public/` — Apache web root with the `index.php` front controller (see `docs/agents/specs/request-handler/front-controller.md`); the built frontend is released under `admin/` there and served at `/admin/` (owned by `frontend`/`infra`, see `docs/agents/specs/frontend/build.md`)
```

Change nothing else in the file.

### Step 2 — Verify

- `grep -rn 'static/' .claude/agents/` returns nothing.
- Both linked spec files exist: `docs/agents/specs/request-handler/front-controller.md` and `docs/agents/specs/frontend/build.md`.
- `git diff` touches only that one line, plus the removal of this plan folder when the PR merges, per the repo convention.

## Files to Change

- `.claude/agents/backend.md` — line 14: the frontend path changes from `static/` to `admin/`, and the line gains the spec pointers.

## Notes

- No specs, code, or other agent definitions change.
- The rejected alternatives (path-only fix, mentioning `.htaccess`, adding a web-root description to `frontend.md`/`infra.md`) are recorded in the issue.
