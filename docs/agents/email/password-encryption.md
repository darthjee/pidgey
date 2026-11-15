# SMTP Password Encryption

Purpose: define how the SMTP connection password is encrypted at rest, keyed, rotated and
decrypted, and how failures are answered.

The SMTP password must be recoverable to authenticate against the SMTP server, so it is
**encrypted**, not hashed (see [smtp-connections.md](smtp-connections.md)). It is
stored in `smtp_connections.password`
([organizations/data-model/smtp-connections.md](../organizations/data-model/smtp-connections.md)).

## Mechanism

- libsodium AEAD **XChaCha20-Poly1305**
  (`sodium_crypto_aead_xchacha20poly1305_ietf_encrypt` / `_decrypt`), with the associated
  data, nonce and byte layout below.
- It is the only candidate with native associated data, a nonce safe to pick at random, no
  hardware dependency, and no extension beyond `sodium`
  ([architecture/dependencies.md](../architecture/dependencies.md)).

Rejected alternatives:

| Alternative | Reason |
| --- | --- |
| `sodium_crypto_secretbox` (XSalsa20-Poly1305) | No associated data; row binding would need hand-built crypto. |
| OpenSSL AES-256-GCM | 12-byte nonce (thin random-nonce margin), needs `ext-openssl`, an API easier to misuse (tag length, options). |
| libsodium AES-256-GCM | Only with hardware AES support; may be missing on the shared production host. |
| `sodium_crypto_secretstream` | Built for streams; overkill for one short password. |
| External KMS / vault | Unavailable on the shared host and adds a network dependency to every send; out of scope for v1. |

## Keys

- **`SMTP_ENCRYPTION_KEY`**: the current key, a **dedicated** key, not derived from
  `SECRET_KEY`. JWT keys rotate cheaply, SMTP keys force re-encrypting every stored
  password, so their rotations stay independent.
- **`PREVIOUS_SMTP_ENCRYPTION_KEYS`**: comma-separated previous keys, used **only to
  decrypt**; encryption always uses the current key.
- Their validation rules (same rules as `SECRET_KEY`, development key, "same as
  `SECRET_KEY`", duplicates) and the `bin/check-config` failure names are in
  [architecture/configuration.md](../architecture/configuration.md#smtp-encryption-keys).
- The keys live only in the environment, never in the database.

Derivations (from the raw environment string `$raw` of each configured key):

| Value | Derivation |
| --- | --- |
| Cipher key (32 bytes) | `hash_hkdf('sha256', $raw, 32, 'pidgey:smtp-password:v1')` |
| Key id (8 bytes) | `hash_hkdf('sha256', $raw, 8, 'pidgey:smtp-key-id:v1')` |

- The raw string is never used as the cipher key itself. The `info` strings give domain
  separation (a derived value cannot collide with any other use of the same raw string) and
  carry a scheme version (`v1`) for a future change.
- Key ids are derived, never assigned manually. Two configured keys sharing a key id (a
  duplicated key) refuse boot.
- The derivations run **once per process**, when the container builds the cipher; the
  derived keys are kept in that object, never derived per call.
- Rejected: a strict 64-hex-char key (`sodium_hex2bin`) or a 43-char base64url key — they
  would give operators a second, different key rule set.

## Byte layout

```text
version (1) || key_id (8) || nonce (24) || ciphertext (n) || tag (16)
```

- `version` is the format version, `0x01`.
- `key_id` is the key id of the key that encrypted the row.
- `nonce` is fresh for every encryption:
  `random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES)` (24 bytes); never
  reused or derived.
- Overhead is 49 bytes; the largest password (255 characters, up to 1020 UTF-8 bytes) needs
  1069 bytes, which fits the `VARBINARY(2048)` column.
- On decrypt, stored bytes **shorter than 50** (49 + a non-empty password) or with an
  **unknown `version`** are malformed.
- The password field rule stays "up to 255 characters".
- Rejected: a byte limit on the password (an odd API/UI rule) and dropping the version / key
  id (still 1060 bytes, and loses rotation).

## Associated data

```text
pidgey:smtp-connection:v1:<organization_id>:<connection_id>
```

- Decimal ids, `:` separator, rebuilt identically on every encrypt and decrypt. It is **not
  stored**.
- A ciphertext copied to another connection or organization fails to decrypt.
- Rejected:
  - reserving the id first (would need UUIDs or a sequence table);
  - binding organization id plus a random per-row value (an extra column for no gain);
  - binding only the organization id (a ciphertext copied between connections of the same
    organization would still decrypt).
- **Known limit (documented, not defended):** the associated data does not include `host`,
  `port` or `username`. An attacker with database write access could repoint a row to their
  own host and receive the password on the next send; binding those fields would force a
  re-encryption on every host edit, and database write access already allows worse.

## Write path

- **Create:** in the create endpoint's existing transaction, `INSERT` the row with
  `password = NULL`, read the new id (`lastInsertId`), encrypt with the associated data built
  from it, then `UPDATE smtp_connections SET password = ? WHERE id = ?`. Any failure rolls
  the whole create back; a row without its password is never visible.
  - A connection created without credentials skips the encrypt/update step (`NULL` stays
    `NULL`).
- **Update:** the id and organization never change, so no special ordering is needed. A new
  `password` is encrypted with the same associated data and the current key; when no
  password is sent, the stored bytes are kept as is (no re-encryption).
- `NULL` and the empty string are never encrypted.
- The password is never trimmed or normalized; it is encrypted as its exact UTF-8 bytes.
- Every encryption uses a fresh nonce, even for the same password.
- **Encryption failure** (sodium or `random_bytes` error): the transaction is rolled back and
  the endpoint answers `500 INTERNAL_ERROR`; the real error is logged without any password
  data.

## Read path

- Only the SMTP transport (the test email now, `POST /v1/messages` later) decrypts and
  receives the plain password. Endpoints, models and responses never hold it after the
  write.
- List and show never decrypt: `hasPassword` is `password IS NOT NULL`.
- On decrypt, the key is looked up by the stored key id among the current and previous keys.
- **Any decrypt failure** — wrong or removed key, corrupt bytes, associated-data mismatch
  (all fail the tag check, and AEAD cannot tell them apart), malformed bytes, unknown
  `version`, unknown key id — gets the same answer:
  `409 SMTP_PASSWORD_UNREADABLE` with the fixed `error.message`
  `The stored SMTP password could not be decrypted; set it again`
  (see [failures.md](failures.md)). The SMTP server is **never contacted**.
  - 409: the resource's state blocks the action and the client can fix it.
  - Rejected: `500 INTERNAL_ERROR` (not actionable) and `502 SMTP_AUTH_FAILED` without
    contacting the server (blames the server for a local key problem).
- **Logging:** one operator log line per failure, `smtp-password-decrypt-failed`, at error
  level, with the connection id, the organization id and the key id tried. Never the stored
  bytes, the key or any plain text.
- **`hasPassword` stays `true`:** bytes are still stored; "no password" and "broken
  password" stay distinguishable.
- **Recovery:** `PATCH` with a new `password` overwrites the bytes, encrypted with the
  current key; clearing `username` and `password` sets the column to `NULL`.
- Detection happens only at send time (or with `bin/reencrypt-smtp-passwords --check`).
- **Frontend:** shows the `error.message` on the test-email action like any other error; no
  special UI in v1.

## Rotation

1. **Lazy re-encryption:** every write of a new `password` uses the current key. No
   re-encryption in the send path (it would make sends write to the database).
2. **CLI `bin/reencrypt-smtp-passwords`:** re-encrypts every row whose key id is not the
   current key's, with that row's associated data.
   - One transaction per row, with `SELECT … FOR UPDATE`; the key id is re-checked after the
     lock and an already-current row is skipped, so a newer write is never overwritten.
   - Idempotent; runs with the normal runtime database user; does not change `updated_at`.
   - An unreadable row is skipped, processing continues, and its id is reported.
   - `--check`: dry run — decrypts every row, changes nothing.
   - Output: counts of re-encrypted (or due, with `--check`), already current, and unreadable
     rows, plus the unreadable ids; never any password data.
   - Exit `0` when nothing is unreadable, `1` otherwise.
3. The release does **not** run the command automatically.
4. The operator procedure is the runbook in
   [deployment/operations.md](../../deployment/operations.md) (section "Rotate
   `SMTP_ENCRYPTION_KEY`"): generate a new key → move the old one to
   `PREVIOUS_SMTP_ENCRYPTION_KEYS` and set the new one as `SMTP_ENCRYPTION_KEY` → run
   `bin/reencrypt-smtp-passwords` → once `--check` reports no old-key rows, remove the old
   key.

## Security rules

1. **Nonce:** a fresh 24-byte nonce per encryption from
   `random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES)`; never reused or
   derived.
2. **Decrypt only where needed:** only the SMTP transport (test email now,
   `POST /v1/messages` later) receives the plain password. Endpoints, models and responses
   never hold it after the write.
3. **No leaks:** `#[SensitiveParameter]` on every parameter carrying the plain password, the
   raw key or the derived key; `DecryptionFailed` has a fixed message with no bytes, key or
   plain text; nothing about the password or key is logged; `bin/check-config` names a bad
   key variable but never prints its value.
4. **Memory wiping:** best-effort `sodium_memzero` on the plain password after the transport
   uses it — hygiene, not a guarantee (PHP copies strings).
5. **No custom comparisons:** authentication-tag verification is libsodium's (`..._decrypt`
   returning `false`); no hand-written constant-time code.
6. **Key custody:** the key lives only in the environment (the production env file on the
   host), never in the database; dumps and backups hold only ciphertext. Losing the key means
   every stored password must be re-entered, so the key is backed up separately from the
   database.
7. **Threat model:**
   - Protected: database leaks (dump, backup, read-only SQL injection) and ciphertext copied
     across rows or organizations.
   - Not protected: an attacker with database write access repointing `host` (see
     [Associated data](#associated-data)), or with access to the environment or the
     process.

Performance: encrypt/decrypt cost is microseconds; it runs on create/update (only when a
password is sent) and once per send. The HKDF derivations run once per process.

## Edge cases

**Write path**

1. **Whitespace / unusual characters:** never trimmed or normalized; encrypted as its exact
   UTF-8 bytes (NUL and control characters included), within the existing field rules.
2. **Same password sent again:** always re-encrypted with a fresh nonce; no "unchanged" check
   (it would need a decrypt on the write path).
3. **`username` / `host` / `port` changed without a password:** stored bytes kept; still
   valid, since the associated data holds only the ids.
4. **Clearing credentials:** empty `username` and `password` → column `NULL`; an empty string
   is never encrypted; the "both set or both empty" rule is unchanged.
5. **Encryption failure on create/update** (sodium or `random_bytes` error): transaction
   rolled back, `500 INTERNAL_ERROR`, real error logged without any password data.
6. **Moving a connection to another organization:** no endpoint allows it; any future move
   must decrypt and re-encrypt (the organization id is in the associated data).

**Read path**

7. **No password stored:** no decrypt; the test email connects without AUTH.
8. **Unknown `version` byte** (e.g. a deploy rollback): malformed →
   `409 SMTP_PASSWORD_UNREADABLE`.
9. **Database restored under a different key set:** unreadable → `409`; the operator restores
   the old key as a previous key or re-enters the passwords.

**Rotation and CLI**

10. **Old key removed before running the command:** its rows answer `409`; the runbook
    requires `--check` first, which lists exactly those rows.
11. **Current key also in `PREVIOUS_…`, or a previous key listed twice:** key-id collision →
    boot refused (`duplicate key`).
12. **A previous key equal to `SECRET_KEY`:** boot refused — the "same as `SECRET_KEY`" rule
    applies to every SMTP key (`PREVIOUS_SMTP_ENCRYPTION_KEYS: same as SECRET_KEY`).
13. **Command concurrent with a `PATCH` on the same row:** one transaction per row with
    `SELECT … FOR UPDATE`, re-checking the key id after the lock; an already-current row is
    skipped, so a newer write is never overwritten.
14. **Command hits an unreadable row:** skipped, processing continues, its id is reported,
    exit `1`.
15. **Deploy swap mid-request:** a request completes with the configuration it booted with;
    rows written with the new key are readable once the new release is live, and the old key
    stays in `PREVIOUS_…` until the runbook removes it.

## Testable rules

- Round-trip: encrypt then decrypt returns the exact password bytes.
- Two encryptions of the same password differ (fresh nonce).
- Tampering with any byte of the stored value fails to decrypt.
- Decrypting with the wrong key fails.
- An associated-data mismatch (other connection id or other organization id) fails.
- An unknown key id and malformed bytes (shorter than 50 bytes, unknown `version`) fail as
  `SMTP_PASSWORD_UNREADABLE`.
- A value encrypted with a key now listed in `PREVIOUS_SMTP_ENCRYPTION_KEYS` decrypts.
- `bin/reencrypt-smtp-passwords` re-encrypts old-key rows with the current key, is
  idempotent, `--check` changes nothing, and an unreadable row makes it exit `1`.
- No password, key or stored bytes appear in logs or exception messages.
