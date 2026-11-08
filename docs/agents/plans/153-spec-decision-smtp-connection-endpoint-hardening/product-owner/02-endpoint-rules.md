# Endpoint rules: redirection, deactivate, concurrency

Decisions 2, 4 and 6 in `api/endpoints/pidgey-admin/smtp-connections.md`.

- **Credential redirection:** add the rule to the field rules / `PATCH` section: when the
  merged state has credentials and `host`, `port` or `encryption` changes, the request must
  carry a non-empty `password` or clear both; otherwise `400 VALIDATION_FAILED` —
  `password must be sent again when host, port or encryption changes`. Changing `name`,
  `fromAddress` or `username` alone does not require it. Update the known limitation, edge
  cases and testable rules; remove the pending bullet here and in `api/pending.md`.
- **`deactivate`:** add `POST /smtp-connections/:id/deactivate.json` (no body; one
  transaction: lock the organization row, load the target, `404` if unknown, set
  `active = false`; `200` with the item; idempotent; operator log
  `smtp-connection-deactivate`). Add it to the route table here and in
  `api/endpoints/pidgey-admin.md` (route table, operator log actions), and to
  `email/smtp-connections.md` (Active connection). Replace "deleting the active connection is
  the only way to have none" accordingly.
- **Concurrency:** test email uses the snapshot read at its start; the answer and the log
  line (with the connection id) are produced even if the row was edited or deleted during
  the send. `PATCH` locks the connection row (`SELECT … FOR UPDATE`) in its transaction for
  read-merge-validate-write, does not take the organization lock, and never writes
  `active`. Add edge cases and testable rules.

## Files to Change
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md`
- `docs/agents/specs/api/endpoints/pidgey-admin.md`
- `docs/agents/specs/email/smtp-connections.md`
- `docs/agents/specs/api/pending.md`
