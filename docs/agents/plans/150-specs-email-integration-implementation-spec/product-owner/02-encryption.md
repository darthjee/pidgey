# Encryption detail file

Create `docs/agents/specs/implement-email/encryption.md` (owner #151).

Building block: **password encryption service** — encrypts the SMTP password on create/update
and decrypts it for the transport; proposed home e.g. `Pidgey\Infra\` or a new
`Pidgey\Crypto\` namespace (proposal). Collaborators: `SmtpConnectionRepository`,
`SmtpConnection::$encryptedPassword`, `ConfigValidator`, `SecretKeys` (precedent for key
validation). Governing specs: `email/smtp-connections.md` (Security),
`organizations/data-model/smtp-connections.md` (`password VARBINARY(1024)`),
`architecture/configuration.md`.

Shared contract (what it carries): encrypt input = plain password + associated data
(connection id, organization id); output = stored bytes (nonce + ciphertext, plus key id if
adopted); decrypt input = stored bytes + associated data; output = plain password or a
decryption failure.

Open items (`**Pending decision:**`):
- encryption mechanism — *working assumption, to be confirmed in #151*: libsodium AEAD
  XChaCha20-Poly1305, fresh random nonce per write, associated data binding the row;
- create-flow ordering: the id is known only after insert, but the associated data needs it;
- key source: dedicated key (e.g. `SMTP_ENCRYPTION_KEY`) vs derived from `SECRET_KEY`;
- key rotation: key id/version with the ciphertext, previous keys, re-encryption;
- undecryptable stored password: answer of test email / sends, logging, `hasPassword`;
- configuration and deploy impact: boot validation, `bin/check-config`, `.env`, CircleCI
  deploy variables.

## Files to Change
- `docs/agents/specs/implement-email/encryption.md` — new detail file.
