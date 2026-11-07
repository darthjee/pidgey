# SMTP connections and failures

Point the connection spec at the new file and add the new error code.

- `email/smtp-connections.md`, Security: keep the three bullets, add that the mechanism, keys
  and rotation are defined in [password-encryption.md](password-encryption.md). Remove the
  Pending decision "Encryption mechanism for the stored password (e.g.
  `sodium_crypto_secretbox`)"; keep every other Pending decision.
- `email/failures.md`: add the row
  `| SMTP_PASSWORD_UNREADABLE | The stored SMTP password could not be decrypted (wrong or removed key, corrupt bytes, associated-data mismatch); the server is never contacted. | 409 |`
  placed before the `SMTP_CONNECTION_FAILED` row, and add it to the fixed-message table with
  `The stored SMTP password could not be decrypted; set it again`. State that it applies to
  the test email and `POST /v1/messages`.

## Files to Change

- `docs/agents/specs/email/smtp-connections.md` — Security link, Pending decision removed.
- `docs/agents/specs/email/failures.md` — new code and fixed message.
