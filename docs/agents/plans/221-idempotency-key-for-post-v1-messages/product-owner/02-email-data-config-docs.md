# Email, data-model and configuration docs

Record the key step in the send flow, the new table in the data model, and the new `check-config` rule.

- `email/send-flow.md`: the key step after step 3 (validation), when the header is present; the replay answering before the connection resolution; the `idempotency` field (`none` / `reserved`) in the attempt-log table; note that the "messages log table" pending decision stays open (idempotency rows hold answers, not messages).
- `organizations/data-model/idempotency-keys.md` (new, same shape as `smtp-test-limits.md`): columns, keys, TTL, lease, lazy cleanup, what is never stored (raw key, message content). Add its row to `organizations/data-model.md`.
- `persistence/schema-migrations.md`: add `0012_create_idempotency_keys.sql` where migrations are listed.
- `architecture/configuration.md`: under the `SMTP_TOTAL_TIMEOUT` rules, the new limit (`< 300`, the idempotency lease) and its `check-config` failure line.
- `deployment/known-gaps.md`: where the proxy-timeout gap (the proxy answering `504` while PHP keeps sending) is described, add that a retry with the same `Idempotency-Key` replays the stored outcome.

## Files to Change

- `docs/agents/email/send-flow.md` — key step and log field.
- `docs/agents/organizations/data-model/idempotency-keys.md` — new.
- `docs/agents/organizations/data-model.md` — index row.
- `docs/agents/persistence/schema-migrations.md` — migration entry.
- `docs/agents/architecture/configuration.md` — lease rule.
- `docs/agents/deployment/known-gaps.md` — proxy-timeout note.
