# Issue: Remove the deployment specs

## Description
Part of epic #95. The implementation docs (`docs/agents/deployment.md` and `docs/agents/deployment/*.md`, sub-issue #101, closed) now describe the deployment as built. Remove the deployment specs (`docs/agents/specs/deployment.md` and `docs/agents/specs/deployment/*.md`) so there is a single reference.

## Problem
Two documents describe the same deployment, and they will drift apart. Some content exists only in the specs today:
- the pipeline pending decisions (frontend coverage to Codacy; `build_images` stays future work);
- the rationale behind decisions, mostly in `security.md` (HSTS, database users, host keys, deploy key, CI context, ...). `deployment/server.md` links to it at `specs/deployment/security.md#https`.

Many files link to the specs, including the infra agent's instructions (`.claude/agents/infra.md`).

## Expected Behavior
- `docs/agents/specs/deployment.md` and `docs/agents/specs/deployment/` no longer exist.
- `grep -rn "specs/deployment\|\.\./deployment" docs AGENTS.md .claude` returns no link to a removed file. Issue and plan files under `docs/agents/issues` and `docs/agents/plans` are historical and are not touched.
- No decision, rationale, pending decision or recommendation from the specs is lost: each one is in the deployment docs or in the spec that owns it.
- Future deployment requirements are written in GitHub issues and plans. The deployment docs are updated when a change ships. `docs/agents/specs.md` and `docs/agents/contributing.md` say so.

## Solution
1. **Move what exists only in the specs**:
   - **Rationale:** add short `Why:` notes to the matching sections of `docs/agents/deployment/*.md`, the way `deployment/pipeline.md` already does. Covers at least HSTS, database users, host keys, deploy key, CI context and its restriction, `.env` permissions and what ships.
   - **Pipeline pending decisions** (frontend coverage to Codacy; `build_images` stays future work): add them to `deployment/known-gaps.md`.
   - **Proxy rate limiting:** the recommendation from `production.md` is already in `deployment/known-gaps.md`; check it is complete. The auth rate-limit pending decision stays owned by `specs/api/pending.md`.
   - No new spec or decisions page.
2. **Repoint or drop every link** to the removed files. At the time of writing, links are in:
   - `docs/agents/specs.md` (index row: drop it, and point to `deployment.md`), `docs/agents/contributing.md`;
   - `docs/agents/deployment/server.md` (HSTS decision link);
   - `.claude/agents/infra.md` ("read `specs/deployment.md` before changing anything" and its Pending decisions list: point at `docs/agents/deployment.md` and `known-gaps.md`);
   - `specs/docker/makefile.md`, `specs/docker/images.md`;
   - `specs/frontend/build.md`, `specs/frontend/testing.md`, `specs/testing/backend.md`;
   - `specs/request-handler/rewrite.md`;
   - `specs/architecture/repository-layout.md`, `specs/architecture/configuration.md`, `specs/architecture/database.md`;
   - `specs/api/pending.md`, `specs/api/endpoints/operational.md`, `specs/api/endpoints/auth/session.md`.

   Re-check with `grep` at implementation time. Repoint each anchor to the matching section of the deployment docs, not just the file.
3. **Write down where future deployment requirements go** (GitHub issues and plans; docs updated on ship) in `docs/agents/specs.md` and `docs/agents/contributing.md`.
4. Delete the spec files.

## Benefits
- One source of truth for the deployment.
- Agents (infra in particular) read the docs that match the code, not a spec that may be out of date.
- The reasons behind each decision stay next to the decision.
