# Product Owner Plan: Specs: request-handler index and spec wiring

Main plan: [plan.md](plan.md)

## Overview
Docs-only change. Create `docs/agents/specs/request-handler.md` (`Purpose:` line + annotated flow diagram + parts table) and six stub detail files under `docs/agents/specs/request-handler/`. Index the area in `docs/agents/specs.md`, and update the specs that describe the front controller so they link to the new area and use the terms "request handlers" and "middlewares".

## Context
- Part of Epic #55. Sibling issues #57–#62 fill in the six detail files later. This issue only creates them as stubs, so every link works and sibling PRs never conflict on the index.
- Spec conventions (`docs/agents/specs.md`): hub → `specs/<name>.md` index → `specs/<name>/*.md` details. Every file starts with a title and a one-line `Purpose:` statement. Undecided items are marked **Pending decision**. English only.
- **Do not change any decision from #55. Do not rewrite current-state wording** ("stub", "placeholder"). Only add links.

## Steps

- [01 — Create the request-handler index and stub detail files](product-owner/01-create-index-and-stubs.md)
- [02 — Index the area in specs.md](product-owner/02-index-in-specs-hub.md)
- [03 — Update architecture/backend.md](product-owner/03-update-backend-spec.md)
- [04 — Link from overview/components.md and repository-layout.md](product-owner/04-link-other-specs.md)
- [05 — (architect) Update the agent guides](product-owner/05-architect-agent-guides.md)
- [06 — Verify links and terminology](product-owner/06-verify.md)

## Notes
- **Step 05 is outside product-owner's scope.** `docs/agents/architecture.md` and `docs/agents/folder-structure.md` are owned by `architect`, so the architect applies that step directly instead of dispatching it.
- **No code changes.** No CI job lints Markdown (CircleCI runs `composer lint` and `npm run lint` only), so verification is the manual check in step 06.
- **Comments already posted.** The sibling/Epic sync rules are already posted as comments on #55 and #57–#62. Nothing to do here.
- **Separate issue.** The stale `static/` path in `.claude/agents/backend.md` is tracked in #63. Do not fix it here.
