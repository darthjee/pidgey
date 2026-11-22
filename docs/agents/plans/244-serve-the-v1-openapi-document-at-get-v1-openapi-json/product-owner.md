# Product-owner Plan: Serve the /v1 OpenAPI document at GET /v1/openapi.json

Main plan: [plan.md](plan.md)

## Shared contracts

Documents what `backend` produces in [plan.md](plan.md#shared-contracts). The docs and `source/openapi/v1.json` must agree on every route, status, header and rule.

## Steps

- [01 — Document the route and its exceptions](product-owner/01-document-the-route-and-its-exceptions.md)
- [02 — Close the spec's Points to reconcile](product-owner/02-close-the-specs-points-to-reconcile.md)

## Notes

- `architect` adds `source/openapi/` to `docs/agents/folder-structure.md` and `docs/agents/architecture/repository-layout.md` (owner `backend`), in the same change set.
- Ship these doc changes in the same PR as the backend code.
