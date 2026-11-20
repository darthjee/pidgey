# Product Owner Plan: Enhance the Node client specs: performance and security

Main plan: [plan.md](plan.md)

## Overview
Write the #225 decisions into the spec files under `docs/agents/specs/node-client/` and the hub `docs/agents/specs/node-client.md`. No code, no as-built docs (`docs/agents/clients/` does not exist yet), no change to `AGENTS.md` or `.claude/agents/`.

## Context
- The specs were written by #224 (merged). Their conventions: a decision replaces its `**Pending decision** (#N)` marker in the file where it lands; each decision lives in one file only (others link to it); examples use `pdg_example…` tokens and `example.com` hosts only; the specs are the source of truth (the body of #219 is not kept in sync).
- The issue file (`docs/agents/issues/225-…md`, section `## Solution`) holds the exact decisions to transcribe; this plan says where each goes.
- The `security` agent reviews the PR (read-only); it owns no file.

## Steps

- [01 — Supply chain in packaging.md](product-owner/01-supply-chain.md)
- [02 — CLI token in cli.md and testing.md](product-owner/02-cli-token.md)
- [03 — Public OpenAPI route in openapi.md and testing.md](product-owner/03-openapi-route.md)
- [04 — Re-point markers to #231, hub table, consistency pass](product-owner/04-markers-and-hub.md)

## CI Checks
- Docs only: no CI job covers `docs/agents/specs/`. Verify manually that every relative link in the touched files resolves (`grep -o '](\S*)'` and check each path) and that no `(#225)` marker remains.

## Notes
- Do not settle anything belonging to #231 (cleartext `http://`, retained response body, stdin cap, connection reuse / worst-case duration) or to #220 / #222 / #223 / #226; only re-point or link to them.
- If a decision conflicts with an as-built doc under `docs/agents/api/`, the as-built doc wins for current behaviour: list the conflict in the PR description instead of editing the as-built doc.
