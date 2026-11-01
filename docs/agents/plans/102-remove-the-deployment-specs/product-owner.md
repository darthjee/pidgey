# Product-owner Plan: Remove the deployment specs

Main plan: [plan.md](plan.md)

## Shared contracts

Use the [link target map](plan.md#link-target-map) to repoint links. The architect creates
the target sections (including the new `deployment/pipeline.md#codacy`,
`deployment/server.md#database-users`, `deployment/server.md#https`) first. Touch only
`docs/agents/specs.md` and `docs/agents/specs/**`.

## Implementation Steps

### Step 1 — Repoint every spec link to the deployment docs

Replace each link into `specs/deployment/` with its target from the map, keeping the anchor
precise. Correct the relative depth: from `specs/<area>/x.md` the docs are at
`../../deployment/…`, from `specs/api/endpoints/x.md` at `../../../deployment/…`, and from
`specs/api/endpoints/auth/x.md` at `../../../../deployment/…`. Reword surrounding text that
calls them "the deployment specs" (e.g. `request-handler/rewrite.md`: "The deployment specs
describe this target" → "The deployment docs describe…").

Files with links at the time of writing (re-check with `grep -rn "deployment" docs/agents/specs docs/agents/specs.md`):
`specs/docker/makefile.md`, `specs/docker/images.md`, `specs/frontend/build.md`,
`specs/frontend/testing.md`, `specs/testing/backend.md`, `specs/request-handler/rewrite.md`,
`specs/architecture/repository-layout.md`, `specs/architecture/configuration.md`,
`specs/architecture/database.md` (line 54 → `server.md#database-users`; line 52 already points
at the docs, check its depth), `specs/api/pending.md`, `specs/api/endpoints/operational.md`,
`specs/api/endpoints/auth/session.md`.

In `docs/agents/specs.md`, remove the `deployment` index row and drop "how it is deployed"
from the intro. Add one line saying deployment is documented as built in
[`deployment.md`](deployment.md), and that new deployment requirements go in GitHub issues
and plans.

### Step 2 — Delete the deployment specs

Delete `docs/agents/specs/deployment.md` and `docs/agents/specs/deployment/` (`pipeline.md`,
`release.md`, `server.md`, `security.md`, `edge-cases.md`, `production.md`). Then check that
nothing outside issues and plans links to a removed file:

```bash
grep -rn "specs/deployment\|\.\./deployment/\(release\|security\|edge-cases\|production\)\.md\|(deployment\.md)\|\.\./deployment\.md" \
  AGENTS.md .claude docs/agents --include=*.md | grep -v "docs/agents/issues\|docs/agents/plans"
```

Every remaining hit must resolve to an existing file and anchor under `docs/agents/deployment/`.

## Files to Change
- `docs/agents/specs.md` — drop the deployment row, add the pointer and the requirements rule.
- `docs/agents/specs/docker/makefile.md`, `docs/agents/specs/docker/images.md` — repoint links.
- `docs/agents/specs/frontend/build.md`, `docs/agents/specs/frontend/testing.md` — repoint links.
- `docs/agents/specs/testing/backend.md` — repoint link.
- `docs/agents/specs/request-handler/rewrite.md` — repoint link and reword.
- `docs/agents/specs/architecture/repository-layout.md`, `configuration.md`, `database.md` — repoint links.
- `docs/agents/specs/api/pending.md`, `docs/agents/specs/api/endpoints/operational.md`, `docs/agents/specs/api/endpoints/auth/session.md` — repoint links.
- `docs/agents/specs/deployment.md`, `docs/agents/specs/deployment/*.md` — delete.

## Notes
- `specs/api/pending.md` keeps owning the application-level auth rate-limit decision; only its
  link to `production.md` / `security.md#known-gaps` changes.
