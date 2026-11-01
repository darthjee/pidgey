# Architect Plan: Remove the deployment specs

Main plan: [plan.md](plan.md)

## Shared contracts

Produce every target in the [link target map](plan.md#link-target-map), including the **new**
sections `deployment/pipeline.md#codacy`, `deployment/server.md#database-users` and
`deployment/server.md#https`. Touch only architect-owned files (see
[Ownership boundary](plan.md#ownership-boundary)).

## Steps

- [01 — Move decision rationale into the deployment docs](architect/01-move-rationale.md)
- [02 — Add the pipeline pending decisions to known gaps](architect/02-known-gaps.md)
- [03 — Repoint architect-owned links and agent instructions](architect/03-repoint-links.md)
- [04 — Say where future deployment requirements go](architect/04-future-requirements.md)

## Notes

- Before starting, diff each `docs/agents/specs/deployment/*.md` section against the docs. The
  goal is that nothing is lost; bringing a spec section across almost unchanged is fine when
  the docs have nothing equivalent.
- Several facts are only in the specs: `Database access in CI`, `Codacy`, `Settled decisions`
  (pipeline), `Never committed` / `To confirm` (server), `Logs`, `What ships`, and the
  `Third-party code` reasons (security). Each one needs a home or an explicit reason to drop it.
- Keep the docs' style: describe what is built, with a short `Why:` paragraph where a decision
  needs justifying (as `deployment/pipeline.md` already does).
