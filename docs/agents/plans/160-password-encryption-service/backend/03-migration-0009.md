# Migration 0009

Widen the stored password column, per
`docs/agents/specs/organizations/data-model/smtp-connections.md#migrations`:
`0009_widen_smtp_connections_password.sql` changes `smtp_connections.password` to
`VARBINARY(2048) NULL`. Update any schema test that asserts the column.

Integration test: a 255-character 4-byte UTF-8 password encrypted by `PasswordCipher`
(1069 bytes) stores and reads back unchanged through `SmtpConnectionRepository`.

## Files to Change

- `source/migrations/0009_widen_smtp_connections_password.sql` — new.
- `source/tests/integration/Schema/...` — column assertion, if present.
- `source/tests/integration/Repository/SmtpConnectionRepositoryTest.php` — largest-password round trip.
