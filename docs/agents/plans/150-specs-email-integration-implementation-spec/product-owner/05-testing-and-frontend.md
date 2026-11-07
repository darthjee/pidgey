# Testing and frontend detail files

Create `docs/agents/specs/implement-email/testing.md` and
`docs/agents/specs/implement-email/frontend.md` (both owned by #154).

`testing.md` — building block **test infrastructure**. Governing specs: `email/testing.md`,
`testing/backend.md`, `docker/compose.md`, `testing/definition-of-done.md`. Open items
(`**Pending decision:**`):
- Mailpit with auth (`MP_SMTP_AUTH_FILE`, one or two instances);
- TLS paths: test CA + certificate for the Mailpit hostname in `source/tests/fixtures/`, CA
  injected through configuration, never disabling verification;
- fake SMTP server: in-memory stream for unit tests, `stream_socket_server` fixture for
  timeouts and "no STARTTLS";
- CircleCI services and local compose / Makefile changes;
- required coverage: encryption (round-trip, nonce, tampering, wrong key, associated-data
  mismatch), log capture of secrets, concurrent `activate`, endpoint "Testable rules".

`frontend.md` — building block **frontend wiring** of the #43 screens
(`frontend/assets/js/components/resources/smtpConnections/`, `SmtpConnectionsClient`).
Governing specs: `frontend/screens.md#smtp-connections-screens`,
`frontend/api-compatibility.md`, `api/endpoints/pidgey-admin/smtp-connections.md`. Open items
(`**Pending decision:**`):
- divergences between the screens / client and the real API (field names, error envelope,
  `hasPassword`, `201` on test, `error.smtp` display) — comparison done by #154, only listed
  here;
- `error.smtp.message` rendered as text only;
- definition of done: Jasmine specs on real shapes + manual end-to-end check in `make dev`.

## Files to Change
- `docs/agents/specs/implement-email/testing.md` — new detail file.
- `docs/agents/specs/implement-email/frontend.md` — new detail file.
