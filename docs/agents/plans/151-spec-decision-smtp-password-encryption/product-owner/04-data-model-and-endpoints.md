# Data model and endpoints

Update the column and the endpoint behavior that depends on encryption.

- `organizations/data-model/smtp-connections.md`: `password` becomes `VARBINARY(2048)`, note
  "Encrypted at rest, see email/password-encryption.md; never returned by the API", and the
  migration `0009_widen_smtp_connections_password.sql`
  (`ALTER TABLE smtp_connections MODIFY password VARBINARY(2048) NULL`; merged migrations are
  never edited).
- `api/endpoints/pidgey-admin/smtp-connections.md`:
  - Create: in its transaction, insert with `password = NULL`, then encrypt with the new id
    and update; link `email/password-encryption.md`.
  - Update: a new `password` is encrypted (fresh nonce, current key); without one the stored
    bytes are kept.
  - `hasPassword`: stays `true` when the stored password cannot be decrypted.
  - Test email: answers `409 SMTP_PASSWORD_UNREADABLE` when the stored password cannot be
    decrypted (failure table and testable rules).

## Files to Change

- `docs/agents/specs/organizations/data-model/smtp-connections.md` — column size, migration.
- `docs/agents/specs/api/endpoints/pidgey-admin/smtp-connections.md` — create ordering,
  update, `hasPassword`, new test-email failure.
