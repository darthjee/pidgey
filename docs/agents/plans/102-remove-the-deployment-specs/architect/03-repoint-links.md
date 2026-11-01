# Repoint architect-owned links and agent instructions

Update every architect-owned reference to the removed specs, using the
[link target map](../plan.md#link-target-map):

- `docs/agents/deployment/server.md`: the HSTS link to `../specs/deployment/security.md#https`
  becomes the new `#https` section.
- `docs/agents/contributing.md`: `build_images` "still planned in specs/deployment/pipeline.md"
  becomes a link to `deployment/known-gaps.md`. Also fix the existing broken relative link
  `deployment/pipeline.md` in the `build_backend` row; it already points at the docs, so check
  it resolves.
- `.claude/agents/infra.md`: replace "read `docs/agents/specs/deployment.md` (index of
  `deployment/*.md`)" with `docs/agents/deployment.md`. Rewrite the Pending-decision list
  (PHP minor version, registry, hosting target, image tagging, deploy-time migrations), which
  is stale: hosting target and deploy-time migrations are settled. Point at
  `deployment/known-gaps.md` and the docker spec for what is still open.
- `.claude/agents/architect.md`: if its documentation table lacks a `Deployment` row, add one
  (owner `architect`).
- `AGENTS.md`: rules 1–2 say specs are the source of truth. Add that deployment has no spec:
  `docs/agents/deployment.md` describes it as built (see step 04).

Then run
`grep -rn "specs/deployment" AGENTS.md .claude docs/agents --include=*.md | grep -v "docs/agents/issues\|docs/agents/plans"`
and check that only files the product-owner owns are left.

## Files to Change
- `docs/agents/deployment/server.md` — HSTS link.
- `docs/agents/contributing.md` — `build_images` link and the CI table row link.
- `.claude/agents/infra.md` — read-first instruction and pending-decision list.
- `.claude/agents/architect.md` — documentation table, if needed.
- `AGENTS.md` — source-of-truth rule exception for deployment.
