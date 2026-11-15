# Fix links inside the moved tree

Commit 2. Edit only link targets inside the moved files and the hub left behind.

1. **Escaping links (26):** in the moved files, links that pointed outside the old tree have one `../` too many. Retarget each by explicit path (anchors kept):
   - `docker/images.md` (4), `docker/makefile.md`, `frontend/testing.md`, `frontend/build.md`: `../../deployment/pipeline.md…` → `../deployment/pipeline.md…`
   - `request-handler/rewrite.md`: `../../deployment/server.md` → `../deployment/server.md`
   - `testing/backend.md`: `../../deployment/operations.md` → `../deployment/operations.md`
   - `architecture/repository-layout.md` (4), `architecture/configuration.md` (4), `architecture/database.md` (4): `../../deployment…` → `../deployment…`
   - `api/pending.md`: `../../deployment/known-gaps.md` → `../deployment/known-gaps.md`
   - `api/endpoints/operational.md` (2): `../../../deployment…` → `../../deployment…`
   - `api/endpoints/auth/session.md`: `../../../../deployment/known-gaps.md` → `../../../deployment/known-gaps.md`
   - `email/password-encryption.md`: `../../deployment/operations.md` → `../deployment/operations.md`
   Find them with a link-resolve check rather than trusting the counts; any link resolving outside `docs/agents/` after the move is one of these.
2. **Hub left behind (5):** `docs/agents/specs/architecture.md` links `architecture/{repository-layout,backend,database,dependencies,configuration}.md` → `../architecture/<file>.md`.
3. **Pre-existing broken anchors (2):** `architecture/dependencies.md` (`configuration.md#bin-check-config`) and `architecture/configuration.md` (`#bin-check-config`) → `#bincheck-config` (slug of `` `bin/check-config` ``, the form `deployment/pipeline.md` already uses).
4. Leave the 4 directory links `../api/endpoints/` in `request-handler/*` as they are (still valid).
5. Run the link check over the moved tree + `docs/agents/specs/`: zero broken links/anchors inside them.

Commit message: `docs(specs): fix links after moving the spec tree`.

## Files to Change
- `docs/agents/docker/images.md`, `docs/agents/docker/makefile.md` — escaping links.
- `docs/agents/frontend/testing.md`, `docs/agents/frontend/build.md` — escaping links.
- `docs/agents/request-handler/rewrite.md` — escaping link.
- `docs/agents/testing/backend.md` — escaping link.
- `docs/agents/architecture/repository-layout.md`, `configuration.md`, `database.md`, `dependencies.md` — escaping links; `#bincheck-config` anchors.
- `docs/agents/api/pending.md`, `docs/agents/api/endpoints/operational.md`, `docs/agents/api/endpoints/auth/session.md` — escaping links.
- `docs/agents/email/password-encryption.md` — escaping link.
- `docs/agents/specs/architecture.md` — 5 detail-file links.
