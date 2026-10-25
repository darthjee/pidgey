# Sending and operational endpoints

Complete `api/endpoints/sending.md` (issue Solution, section 7):

- state the documented exceptions to the conventions: `/v1` prefix, no `.json` suffix,
  Bearer token with scope `send`, success `200`; shared error envelope; fields already
  `camelCase`; origin check applies (server callers send no `Origin`);
- link the send flow (`email/send-flow.md`), payload rules (`email/message-scope.md`) and
  failure codes (`email/failures.md`);
- tokens of an organization with **no `approved` user** are refused with the same
  `401 UNAUTHORIZED` as a revoked token; no data change.

Create `api/endpoints/operational.md` (issue Solution, section 8):

- `GET /health.json` → always `200 { status: "ok" }`, touches nothing;
- `GET /ready.json` → `SELECT 1`; `200 { status: "ok", checks: { database: "up" } }` or
  `503 { status: "error", checks: { database: "down" } }`, reason logged only;
- public, no gate, no origin check (`GET`), no cache headers; Docker/deployment wiring out of
  scope.

## Files to Change
- `docs/agents/specs/api/endpoints/sending.md` — complete the contract.
- `docs/agents/specs/api/endpoints/operational.md` — new.
