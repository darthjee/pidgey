# Wire into POST /v1/messages

Plug the service into the `POST /v1/messages` handler built by #237, between payload validation and the active-connection resolution.

- Read `Idempotency-Key` with `Request::header()`. Absent → today's path, untouched.
- Invalid → `400 VALIDATION_FAILED` with the contract's `details` entry.
- Map the decision: replay → the stored status and body with `Idempotent-Replayed: true`; reused → `422 IDEMPOTENCY_KEY_REUSED`; in use → `409 IDEMPOTENCY_KEY_IN_USE` (fixed messages of the contract, through the shared error formatter).
- After the send (and on the connection `409`s), call `finish` with the final status and body. An unexpected exception after the SMTP transaction started stores the `500 INTERNAL_ERROR` envelope before it propagates; one raised before releases the row.
- Add `idempotency` (`none` / `reserved`) to the `smtp-send-attempt` log context.
- Handler tests (the issue's testable rules): no header → no row; store and replay with no transport call; `422`; `409` within the lease and stored `504` past it; `503` releases and the retry sends; `SMTP_TIMEOUT` and `SMTP_PROTOCOL_ERROR` stored; invalid keys (empty, 256 characters, a control character) → `400`; the same key in two organizations sends twice; an expired key sends again; a pre-SMTP `409 NO_SMTP_CONNECTION` stores nothing; a replay still answers after the active connection was deleted.

## Files to Change

- The `POST /v1/messages` handler from #237 (under `source/lib/Http/Handler/`) — the key step and the replay header.
- `source/lib/Email/SendFlow.php` — the `idempotency` log field (passed in by the handler).
- `source/lib/Container.php` — inject the service into the handler.
- The handler's tests from #237 (under `source/tests/unit/Http/` and `source/tests/integration/Http/`) — the cases above.
