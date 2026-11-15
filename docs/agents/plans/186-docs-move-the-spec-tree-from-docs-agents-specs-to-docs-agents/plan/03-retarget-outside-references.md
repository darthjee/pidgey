# Retarget references outside the tree

Commit 3. Path-only edits; no sentence is reworded. Each reference below names a file that moved; rule patterns and ownership mentions of the directory are left untouched.

**Markdown links**

- `docs/agents/specs.md` (9): `specs/<area>.md` → `<area>.md` for `overview`, `persistence`, `request-handler`, `docker`, `organizations`, `email`, `api`, `frontend`, `testing`. `specs/architecture.md` stays.
- `docs/agents/architecture.md`: `specs/overview/components.md` → `overview/components.md`; `specs/request-handler.md` → `request-handler.md`; `specs/request-handler/static-files.md` → `request-handler/static-files.md`; `specs/architecture/dependencies.md` → `architecture/dependencies.md`; `specs/frontend.md` → `frontend.md` (both text and target of each link). Leave the banner's `specs/architecture.md` link (still exists; #187 removes the banner).
- `docs/agents/folder-structure.md`: `specs/architecture/repository-layout.md` → `architecture/repository-layout.md`; `specs/request-handler/front-controller.md` → `request-handler/front-controller.md`; `specs/request-handler/static-files.md` → `request-handler/static-files.md`. Leave the `specs.md, specs/` row text (#192).
- `docs/agents/contributing.md`: `specs/testing/definition-of-done.md` → `testing/definition-of-done.md`. Do **not** touch `specs/client/ApiClient_spec.js` (frontend code example).
- `docs/agents/deployment/known-gaps.md`, `operations.md`, `pipeline.md`, `server.md` (~21): `../specs/<area>/…` → `../<area>/…`, link text and anchors preserved (`#brute-force`, `#key-rotation`, `#secret-keys`, `#compatibility-with-the-previous-release`, `#bincheck-config`, `#reaching-the-instances`, `#production-delivery`, `#env-file`, `#smtp-transport`, `#database-users`).
- `README.md`: `docs/agents/specs/frontend/development.md#first-run` → `docs/agents/frontend/development.md#first-run`; `docs/agents/specs/docker/makefile.md` (text + target) → `docs/agents/docker/makefile.md`.

**Backticked paths (not links)**

- `.claude/agents/backend.md`: `docs/agents/specs/request-handler/front-controller.md`, `docs/agents/specs/frontend/build.md`, `docs/agents/specs/architecture/dependencies.md` → drop `specs/`.
- `.claude/agents/security.md`: `docs/agents/specs/architecture/dependencies.md` → `docs/agents/architecture/dependencies.md`.
- `.claude/agents/email.md`: `docs/agents/specs/email/failures.md`, `docs/agents/specs/email.md`, `docs/agents/specs/email/` → drop `specs/`.
- `.claude/agents/frontend.md`: `docs/agents/specs/frontend.md`, `docs/agents/specs/frontend/`, `docs/agents/specs/api.md` (2) → drop `specs/`.
- `.claude/agents/infra.md`: `docs/agents/specs/docker.md` → `docs/agents/docker.md`.
- `docker-compose.yml`: comment `docs/agents/specs/email/testing.md` → `docs/agents/email/testing.md`.

**Leave untouched**

- Every `docs/agents/specs.md` reference (file unchanged).
- "Do NOT touch `docs/agents/specs/` (`product-owner`)" in `backend.md`, `frontend.md`, `infra.md`; the ownership table in `architect.md`; `product-owner.md`'s lists/rules/description (#193).
- `AGENTS.md`: it only references `docs/agents/specs.md` and the granularity-rule patterns `docs/agents/specs/<name>…` — no edit in this PR (#192).

Finally run the full link check (see plan `## CI Checks`) and the `git grep`; every remaining hit must be in the "leave untouched" categories.

Commit message: `docs(specs): retarget references to the moved spec tree`.

## Files to Change
- `docs/agents/specs.md` — 9 index links.
- `docs/agents/architecture.md` — 5 links.
- `docs/agents/folder-structure.md` — 3 links.
- `docs/agents/contributing.md` — 1 link.
- `docs/agents/deployment/known-gaps.md`, `operations.md`, `pipeline.md`, `server.md` — ~21 links.
- `README.md` — 2 references (3 occurrences).
- `.claude/agents/backend.md`, `security.md`, `email.md`, `frontend.md`, `infra.md` — backticked paths.
- `docker-compose.yml` — one comment.
