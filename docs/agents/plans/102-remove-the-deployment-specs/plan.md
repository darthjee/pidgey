# Plan: Remove the deployment specs

Issue: [102-remove-the-deployment-specs.md](../../issues/102-remove-the-deployment-specs.md)

## Overview

Make the implementation docs (`docs/agents/deployment.md`, `docs/agents/deployment/*.md`) the
single deployment reference. The architect first moves everything that exists only in the
specs (decision rationale, pipeline pending decisions) into those docs, adds the sections other
files will link to, and updates the guides and agent instructions. The product-owner then
repoints every link in the specs to those sections and deletes `docs/agents/specs/deployment.md`
and `docs/agents/specs/deployment/`.

The coordinator (`architect`) is listed as an involved agent on purpose: `docs/agents/deployment/`,
`docs/agents/contributing.md`, `AGENTS.md` and `.claude/agents/*.md` are architect-owned, and
`product-owner` may not touch them.

## Agents involved

- [architect](architect.md) — runs first.
- [product-owner](product-owner.md) — runs after the architect's sections exist.

## Shared contracts

### Link target map

The architect makes sure every target below exists, adding the sections marked **new**. The
product-owner uses this map to repoint links. Paths are relative to `docs/agents/`.

| Old target (`specs/deployment/…`) | New target |
| --- | --- |
| `../deployment.md` (spec index) | `deployment.md` |
| `pipeline.md` | `deployment/pipeline.md` |
| `pipeline.md#check-jobs` | `deployment/pipeline.md#check-jobs` |
| `pipeline.md#deploy-jobs` | `deployment/pipeline.md#deploy-chain` |
| `pipeline.md#codacy` | `deployment/pipeline.md#codacy` (**new**) |
| `release.md` | `deployment/pipeline.md#deploy-script` |
| `release.md#deploy-script` | `deployment/pipeline.md#deploy-script` |
| `release.md#release-release` | `deployment/pipeline.md#deploy-script` |
| `release.md#backend-upload-upload_backend` | `deployment/pipeline.md#exclude-list` |
| `release.md#frontend-upload-upload_frontend` | `deployment/pipeline.md#deploy-chain` |
| `release.md#verification` | `deployment/operations.md#release` |
| `server.md` | `deployment/server.md` |
| `server.md#shared-folder` | `deployment/server.md#folders` |
| `server.md#the-configenv-link` | `deployment/server.md#the-configenv-link` |
| `server.md#host-requirements` | `deployment/server.md#host-requirements` |
| `security.md#database-users` | `deployment/server.md#database-users` (**new**) |
| `security.md#https` | `deployment/server.md#https` (**new**) |
| `security.md#known-gaps`, `production.md` | `deployment/known-gaps.md#no-proxy-rate-limiting` |
| `edge-cases.md#migrations` | `deployment/operations.md#failure-before-the-swap` |
| `edge-cases.md#frontend` | `deployment/server.md#web-directory` |

If the architect has to name a new section differently, it updates this table in
`plan.md` before the product-owner starts.

### Ownership boundary

- `architect`: `docs/agents/deployment.md`, `docs/agents/deployment/*.md`,
  `docs/agents/contributing.md`, `AGENTS.md`, `.claude/agents/*.md`.
- `product-owner`: `docs/agents/specs.md`, `docs/agents/specs/**` (including the deletion).
- Neither touches `docs/agents/issues/` or `docs/agents/plans/` from other issues; old links
  there are historical.
