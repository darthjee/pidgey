# Product Owner Plan: Idempotency-Key for POST /v1/messages

Main plan: [plan.md](plan.md)

## Shared contracts

`product-owner` documents every value of [plan.md](plan.md#shared-contracts) exactly as written there: header names and format, the `400` detail message, both new codes with their statuses and fixed messages, the table, the TTL/lease/batch constants, the flow and store/release rules, the attempt-log field, the `check-config` rule and the client-spec behavior. Copy the values; do not paraphrase codes or messages.

## Steps

- [01 — API docs](product-owner/01-api-docs.md)
- [02 — Email, data-model and configuration docs](product-owner/02-email-data-config-docs.md)
- [03 — Node client specs](product-owner/03-node-client-specs.md)

## CI Checks

- Docs only; run `make tests` if any doc check exists in the suite (CI job: `backend_checks`).

## Notes

- The docs describe behavior that becomes live only after #237 and the backend steps land; state the dependency on #237 where the endpoint itself is described as not yet implemented.
- Final consistency check: `grep -rn -i "idempot" docs/agents` must show no leftover "pending decision" wording for idempotency outside `issues/` and `plans/`.
