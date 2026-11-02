# Architect Plan: Move the frontend build and deploy to `source/static/`

Main plan: [plan.md](plan.md)

## Shared contracts

- The UI is built into `source/static/` (git-ignored, outside the web root) and served at `/` by `StaticFileHandler`. `source/public/` holds only `index.php` and `.htaccess`.
- Dev UI `http://localhost:8030/`, release UI `http://localhost:8080/`.

## Implementation Steps

### Step 1 — Project docs
Update the docs that describe the project as built. Match the wording of `docs/agents/specs/architecture/repository-layout.md`, which is already correct.
- `README.md`: `http://localhost:8030/admin/` → `http://localhost:8030/`.
- `docs/agents/architecture.md`:
  - Frontend bullet: `dist/` is released into `source/static` (`make build-fe-release`) and served at `/` by the PHP static-file handler.
  - `### source/public/`: only the `index.php` front controller and the `.htaccess`.
  - Add a `### source/static/` section: the built frontend, git-ignored, outside the web root.
- `docs/agents/folder-structure.md`:
  - `frontend/` row: released into `source/static` and served at `/`.
  - `public/` row: drop the stale placeholder-stub wording and the `admin/` mention. It holds the `index.php` front controller (link the front-controller spec) and the `.htaccess`.
  - Add a `static/` row.

### Step 2 — Backend agent scope
In `.claude/agents/backend.md`, rewrite the `public/` bullet to say `public/` is the web root with `index.php` and `.htaccess` only. Add a `static/` bullet: the built frontend (owned by `frontend`/`infra`, see `docs/agents/specs/frontend/build.md`), served by `StaticFileHandler`.

## Files to Change
- `README.md`
- `docs/agents/architecture.md`
- `docs/agents/folder-structure.md`
- `.claude/agents/backend.md`

## Notes
- Leave `docs/agents/issues/` and `docs/agents/plans/` alone. They are history.
- At the end, run `grep -rn "public/admin\|/admin/" README.md AGENTS.md docs/agents .claude/agents --include=*.md | grep -v "docs/agents/issues\|docs/agents/plans"`. It should only show hash routes or API paths such as `/admin/users/*.json`.
