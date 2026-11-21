# API docs

Document the header, the replay and the new codes in the API contract, and close the pending item.

- `api/endpoints/sending.md`:
  - a `### Idempotency-Key` subsection under `POST /v1/messages`: format, per-organization scope, 24-hour TTL, the flow and the store/release rules of the shared contract, the `Idempotent-Replayed: true` header, the replay being verbatim (original `timestamp` included), and the limit (SMTP-level ambiguity is replayed, never re-sent);
  - update the Steps line (key step after payload validation);
  - error table rows: invalid key (`400 VALIDATION_FAILED`), `422 IDEMPOTENCY_KEY_REUSED`, `409 IDEMPOTENCY_KEY_IN_USE`;
  - testable rules: the issue's backend list;
  - the "Pending decisions" line no longer lists idempotency.
- `api/conventions/errors.md`: add both codes to the codes table (status, fixed message, endpoint `POST /v1/messages`).
- `api/pending.md`: remove the idempotency pending decision, pointing to `sending.md`.
- `api.md`: the `pending.md` row no longer mentions idempotency.

## Files to Change

- `docs/agents/api/endpoints/sending.md` — the subsection, steps, error rows, testable rules.
- `docs/agents/api/conventions/errors.md` — the two codes.
- `docs/agents/api/pending.md` — item removed.
- `docs/agents/api.md` — index row wording.
