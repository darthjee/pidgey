# Issue: Agents: backend agent references stale static/ frontend path

## Description

Line 14 of `.claude/agents/backend.md` describes the web root and says the built frontend "lands in `static/`":

> `public/` — Apache web root with the `index.php` front controller (built frontend lands in `static/`, owned by `frontend`/`infra`)

The specs release the built frontend under `admin/` in the web root and serve it at `/admin/` (`docs/agents/specs/frontend/build.md` and `docs/agents/specs/architecture/repository-layout.md`). Found while enhancing #56.

## Problem

The backend agent reads its own definition as ground truth. A stale `static/` path can lead it to create, reference or route around a folder that does not exist. It also contradicts the specs the agent is told to follow.

## Expected Behavior

- Line 14 of `.claude/agents/backend.md` names `admin/` (served at `/admin/`) as the place where the built frontend lands, and points to the specs:

  > `public/` — Apache web root with the `index.php` front controller (see `docs/agents/specs/request-handler/front-controller.md`); the built frontend is released under `admin/` there and served at `/admin/` (owned by `frontend`/`infra`, see `docs/agents/specs/frontend/build.md`)

- `grep -rn 'static/' .claude/agents/` returns nothing.

## Solution

- **Scope:** only line 14 of `.claude/agents/backend.md` changes; it gets the wording above.
  - A search of every file in `.claude/agents/` found no other stale `static/` path.
  - `frontend.md` and `infra.md` do not describe the web root, so they stay untouched.
  - No spec changes.
- **Rejected alternatives:**
  - Fixing the path only, without the spec pointers.
  - Also mentioning `.htaccess` (#57) in the line. The spec already covers it.
  - Adding a web-root description to `frontend.md` / `infra.md`. That would be new scope.
- **Edge cases:** `source/public/admin/` is a per-release build output (see the interim rule in `frontend/build.md`). The agent line only describes where it lands.
- **Backward compatibility:** docs only, with no code or behaviour change.
- **Testing:** the `grep` check above.
- **Performance and security:** not applicable.

## Benefits

- The backend agent's definition matches the specs.
- One fewer source of wrong paths when backend work touches the web root (e.g. the front controller, #62).
