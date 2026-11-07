# Implement Email: Password Encryption

Purpose: map the encryption at rest of the SMTP password to its implementation work; the
decisions owned by #151 are settled in the permanent specs linked below.

> Temporary — part of epic #149. Permanent specs win on conflict; update them, not this
> file, when a decision changes the target behavior.

Index: [implement-email.md](../implement-email.md).

## Open items

All settled by #151.

- [x] **Encryption mechanism:** libsodium AEAD XChaCha20-Poly1305, alternatives rejected —
  [email/password-encryption.md](../email/password-encryption.md#mechanism).
- [x] **Create-flow ordering:** insert with `password = NULL`, then encrypt with the new id
  and update, in one transaction; associated data
  `pidgey:smtp-connection:v1:<organization_id>:<connection_id>` —
  [email/password-encryption.md](../email/password-encryption.md#write-path) and
  [Associated data](../email/password-encryption.md#associated-data);
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md#post-smtp-connectionsjson).
- [x] **Key source:** a dedicated `SMTP_ENCRYPTION_KEY`, not derived from `SECRET_KEY`;
  HKDF-derived cipher key —
  [email/password-encryption.md](../email/password-encryption.md#keys).
- [x] **Key rotation:** derived 8-byte key id in the stored bytes,
  `PREVIOUS_SMTP_ENCRYPTION_KEYS` for decryption only, lazy re-encryption on write and
  `bin/reencrypt-smtp-passwords` (with `--check`) —
  [email/password-encryption.md](../email/password-encryption.md#rotation).
- [x] **Undecryptable stored password:** `409 SMTP_PASSWORD_UNREADABLE`, server never
  contacted, `smtp-password-decrypt-failed` log line, `hasPassword` stays `true` —
  [email/password-encryption.md](../email/password-encryption.md#read-path) and
  [email/failures.md](../email/failures.md).
- [x] **Configuration and deploy impact:** key rules, development key, failure names, sodium
  check — [architecture/configuration.md](../architecture/configuration.md#smtp-encryption-keys);
  the keys live in the host's shared `.env` (no CircleCI variable), and the rotation runbook
  is in [deployment/operations.md](../../deployment/operations.md) (section "Rotate
  `SMTP_ENCRYPTION_KEY`").
- [x] **`ext-sodium` as a dependency:** declared in `composer.json`, required extension,
  checked by `bin/check-config` and at web boot —
  [architecture/dependencies.md](../architecture/dependencies.md#backend-composer) and
  [architecture/configuration.md](../architecture/configuration.md#bin-check-config).
- [x] **Column size:** `password VARBINARY(2048)` through migration `0009`; the field rule
  stays 255 characters —
  [organizations/data-model/smtp-connections.md](../organizations/data-model/smtp-connections.md#migrations)
  and [email/password-encryption.md](../email/password-encryption.md#byte-layout).

## Building block: password encryption service

- **Responsibility:** turns the plain SMTP password into the bytes stored in
  `smtp_connections.password` on create and update, and turns those bytes back into the plain
  password for the SMTP transport. It does not decide when a password changes (the endpoints
  do) and never logs or returns the plain password or the key.
- **Governing specs:** [email/password-encryption.md](../email/password-encryption.md);
  [email/smtp-connections.md](../email/smtp-connections.md) (Security);
  [email/failures.md](../email/failures.md) (`SMTP_PASSWORD_UNREADABLE`);
  [organizations/data-model/smtp-connections.md](../organizations/data-model/smtp-connections.md)
  (`password` column, migration `0009`);
  [architecture/configuration.md](../architecture/configuration.md#smtp-encryption-keys);
  [architecture/dependencies.md](../architecture/dependencies.md);
  [api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md)
  (create ordering, write-only `password`, `hasPassword`, test-email `409`).
- **Collaborators:** `Pidgey\Repository\SmtpConnectionRepository` (stores the bytes),
  `Pidgey\Model\SmtpConnection::$encryptedPassword` (holds them), `Pidgey\Infra\Env` and
  `Pidgey\Infra\ConfigValidator` (key loading and boot validation), `Pidgey\Auth\SecretKeys`
  (precedent for key validation and `#[SensitiveParameter]`), `Pidgey\Container` (wiring).

### Implementation work

Names are **proposals** (see the lifecycle rules in [implement-email.md](../implement-email.md)).

1. **Cipher** — `Pidgey\Crypto\PasswordCipher`: encrypt / decrypt as defined in
   [Mechanism](../email/password-encryption.md#mechanism),
   [Byte layout](../email/password-encryption.md#byte-layout) and
   [Associated data](../email/password-encryption.md#associated-data); the failure
   `Pidgey\Crypto\DecryptionFailed` with a fixed message
   ([Security rules](../email/password-encryption.md#security-rules)).
2. **Key set** — `Pidgey\Crypto\EncryptionKeys`, following the `Pidgey\Auth\SecretKeys`
   precedent: the current key and the previous keys, their derived cipher keys and key ids,
   built once per process by the container
   ([Keys](../email/password-encryption.md#keys)).
3. **Configuration** — `ConfigValidator` / `bin/check-config`: the
   [SMTP encryption key rules](../architecture/configuration.md#smtp-encryption-keys), the
   constant `ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY`, and the `sodium` extension
   check at check-config and web boot
   ([`bin/check-config`](../architecture/configuration.md#bin-check-config)).
4. **Migration** — `0009_widen_smtp_connections_password.sql`
   ([data model](../organizations/data-model/smtp-connections.md#migrations)).
5. **CLI** — `bin/reencrypt-smtp-passwords` with `--check`
   ([Rotation](../email/password-encryption.md#rotation)).
6. **Composer and environment** — `"ext-sodium": "*"` in `require` of `source/composer.json`
   ([dependencies.md](../architecture/dependencies.md#backend-composer)); the development
   `SMTP_ENCRYPTION_KEY` in the root `.env`.
7. **Endpoint and transport use** — the create ordering and update rules are wired by the
   connection endpoints, the decrypt and `409 SMTP_PASSWORD_UNREADABLE` by the test email
   ([api/endpoints/pidgey-admin/smtp-connections.md](../api/endpoints/pidgey-admin/smtp-connections.md)).
8. **Tests** — the [testable rules](../email/password-encryption.md#testable-rules).

### Shared contract: password encryption

Used by the connection endpoints (encrypt) and the send flow (decrypt).

| Operation | Input | Output |
| --- | --- | --- |
| Encrypt | the plain password (never trimmed, non-empty); the associated data | the stored bytes, with the current key |
| Decrypt | the stored bytes; the associated data | the plain password, or a decryption failure |

- **Associated data:** `pidgey:smtp-connection:v1:<organization_id>:<connection_id>`
  ([decided](../email/password-encryption.md#associated-data)); not stored, rebuilt
  identically on decrypt.
- **Stored bytes:** `version (1) || key_id (8) || nonce (24) || ciphertext (n) || tag (16)`
  ([decided](../email/password-encryption.md#byte-layout)). Opaque to every other component.
- **Decryption failure:** carries no plain text, no key and no stored bytes; only that
  decryption failed and which key id was tried (for the `smtp-password-decrypt-failed` log
  line). Every cause maps to `409 SMTP_PASSWORD_UNREADABLE`.
- A `NULL` password is never encrypted or decrypted: "no password" stays `NULL`.

### New configuration keys

| Key | Carries | Status |
| --- | --- | --- |
| `SMTP_ENCRYPTION_KEY` | The current encryption key (required). | Decided, in [architecture/configuration.md](../architecture/configuration.md#smtp-password-encryption). |
| `PREVIOUS_SMTP_ENCRYPTION_KEYS` | Previous keys, used only to decrypt. | Decided, in [architecture/configuration.md](../architecture/configuration.md#smtp-password-encryption). |
