# Backend Plan: Idempotency-Key for POST /v1/messages

Main plan: [plan.md](plan.md)

## Shared contracts

Backend produces everything in [plan.md](plan.md#shared-contracts) except the client specs: the headers, the two codes and messages, the table, the constants and normalization, the flow, the attempt-log field and the `check-config` rule. The docs written by `product-owner` state the same values; when they disagree, raise it rather than diverging.

## Steps

- [01 — Migration, model and repository](backend/01-storage.md)
- [02 — Idempotency service](backend/02-idempotency-service.md)
- [03 — Wire into POST /v1/messages](backend/03-wire-send-endpoint.md)
- [04 — check-config lease rule](backend/04-check-config-rule.md)

## CI Checks

- `source/`: `make tests` (CI jobs: `backend_checks`, `backend_tests`)

## Notes

- **Blocked by #237.** Step 03 needs the `POST /v1/messages` handler, its Bearer authentication and payload validation. If #237 is not merged when this issue is picked up, stop after steps 01, 02 and 04 and report the dependency instead of building the endpoint here.
- No lock is held across the SMTP transaction: the reservation relies on the unique index only.
- Times come from `Pidgey\Infra\WallClock` so tests control the lease and the TTL.
