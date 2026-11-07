# Implement Email: Password Encryption

Purpose: map the encryption at rest of the SMTP password to its implementation work and list
the open decisions owned by #151.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

Index: [implement-email.md](../implement-email.md).

## Open items

- [ ] **Pending decision:** encryption mechanism for the stored password (pending in
  [email/smtp-connections.md](../email/smtp-connections.md)).
  *Working assumption, to be confirmed in #151:* libsodium AEAD XChaCha20-Poly1305
  (`sodium_crypto_aead_xchacha20poly1305_ietf_*`), a fresh random nonce per write, and
  associated data binding the ciphertext to its row (connection id and organization id), so a
  ciphertext copied to another row or organization fails to decrypt.
- [ ] **Pending decision:** create-flow ordering. The connection id is known only after the
  insert, but the associated data needs it: insert then encrypt and update in the same
  transaction, reserve the id first, or bind only the organization id (plus a random row
  identifier) instead.
- [ ] **Pending decision:** key source. A dedicated key (e.g. `SMTP_ENCRYPTION_KEY`) or a key
  derived from `SECRET_KEY` (which today signs access-token JWTs, see
  [architecture/configuration.md](../architecture/configuration.md#secret-keys)). A derived key
  ties the SMTP passwords to the JWT key's rotation: rotating `SECRET_KEY` would make every
  stored password undecryptable.
- [ ] **Pending decision:** key rotation. Whether the stored bytes carry a key id / version,
  whether previous keys are accepted for decryption only (as `PREVIOUS_SECRET_KEYS` does for
  JWTs), and how and when stored passwords are re-encrypted with the current key (on next
  write, or by a CLI command).
- [ ] **Pending decision:** undecryptable stored password (wrong or removed key, corrupted
  bytes, associated-data mismatch). The answer of the test email (and later of
  `POST /v1/messages`): a new error code, `INTERNAL_ERROR`, or `SMTP_AUTH_FAILED` without
  contacting the server; what is logged (never the bytes or the key); whether `hasPassword`
  stays `true`.
- [ ] **Pending decision:** configuration and deploy impact of a new key: its boot validation
  (missing, length, development key under a non-development `PHP_ENV`), its
  `bin/check-config` failure names, the development value in the root `.env`, and the
  production / CircleCI deploy variables
  ([architecture/configuration.md](../architecture/configuration.md)).
- [ ] **Pending decision:** `libsodium` as a dependency. Whether `ext-sodium` is declared in
  `composer.json` and listed in [architecture/dependencies.md](../architecture/dependencies.md),
  and checked by `bin/check-config` on the production host.
- [ ] **Pending decision (inconsistency):** column size. `password` is `VARBINARY(1024)`
  ([organizations/data-model/smtp-connections.md](../organizations/data-model/smtp-connections.md)),
  while a password may be 255 characters (up to 1020 bytes in UTF-8,
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#field-rules));
  with a nonce, an authentication tag and a key id the stored bytes can exceed 1024. Either
  the column grows (a migration) or the field rule limits the password in bytes.

## Building block: password encryption service

- **Responsibility:** turns the plain SMTP password into the bytes stored in
  `smtp_connections.password` on create and update, and turns those bytes back into the plain
  password for the SMTP transport. It does not decide when a password changes (the endpoints
  do) and never logs or returns the plain password or the key.
- **Proposed names:** `Pidgey\Crypto\PasswordCipher` (or `Pidgey\Infra\SecretBox`), with a key
  value object `Pidgey\Crypto\EncryptionKeys` following the `Pidgey\Auth\SecretKeys`
  precedent, and a failure `Pidgey\Crypto\DecryptionFailed`. Proposals only.
- **Collaborators:** `Pidgey\Repository\SmtpConnectionRepository` (stores the bytes),
  `Pidgey\Model\SmtpConnection::$encryptedPassword` (holds them), `Pidgey\Infra\Env` and
  `Pidgey\Infra\ConfigValidator` (key loading and boot validation), `Pidgey\Auth\SecretKeys`
  (precedent for key validation and `#[SensitiveParameter]`), `Pidgey\Container` (wiring).
- **Governing specs:** [email/smtp-connections.md](../email/smtp-connections.md) (Security);
  [organizations/data-model/smtp-connections.md](../organizations/data-model/smtp-connections.md)
  (`password` column);
  [architecture/configuration.md](../architecture/configuration.md#secret-keys);
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md)
  (write-only `password`, `hasPassword`).
- **Open items:** every item above.

### Shared contract: password encryption

Used by the connection endpoints (encrypt) and the send flow (decrypt).

| Operation | Input | Output |
| --- | --- | --- |
| Encrypt | the plain password (never trimmed, non-empty); the associated data | the stored bytes |
| Decrypt | the stored bytes; the associated data | the plain password, or a decryption failure |

- **Associated data:** identifies the row the ciphertext belongs to (connection id and
  organization id, *working assumption, to be confirmed in #151*); it is not stored in the
  ciphertext and must be rebuilt identically on decrypt.
- **Stored bytes:** the nonce followed by the ciphertext (with its authentication tag), plus a
  key id / version if rotation adopts one. Opaque to every other component.
- **Decryption failure:** carries no plain text, no key and no stored bytes; only that
  decryption failed (and, if useful for the operator log, which key id was tried).
- A `NULL` password is never encrypted or decrypted: "no password" stays `NULL`.

### New configuration keys

| Key | Carries | Status |
| --- | --- | --- |
| `SMTP_ENCRYPTION_KEY` (proposal) | The current encryption key. | Depends on the key-source item above. |
| `PREVIOUS_SMTP_ENCRYPTION_KEYS` (proposal) | Retired keys, used only to decrypt. | Depends on the key-rotation item above. |

When adopted, both are added to
[architecture/configuration.md](../architecture/configuration.md) by #151, not here.
