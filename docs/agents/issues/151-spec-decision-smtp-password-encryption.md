# Issue: Spec decision: SMTP password encryption

## Description

Settle the **SMTP password encryption** design for the email integration (epic #149) in the permanent specs, so it can be implemented. Builds on `docs/agents/specs/implement-email/encryption.md` (from #150), which lists the open items.

### Context

`docs/agents/specs/email/smtp-connections.md` requires the SMTP password to be encrypted at
rest (it must be recoverable to authenticate) and leaves the mechanism as a **Pending
decision**. The column is `smtp_connections.password VARBINARY(1024)`
(`organizations/data-model/smtp-connections.md`); `SmtpConnection::$encryptedPassword` holds
the ciphertext bytes. `SECRET_KEY` / `PREVIOUS_SECRET_KEYS` (`architecture/configuration.md`)
currently sign access-token JWTs.

## Problem

`email/smtp-connections.md` requires the SMTP password to be encrypted at rest but leaves the mechanism as a **Pending decision**, and `implement-email/encryption.md` lists eight open items: mechanism, create-flow ordering, key source, key rotation, undecryptable password, configuration/deploy impact, `ext-sodium` as a dependency, and the column size (a 255-character password plus nonce, tag and key id overflows `VARBINARY(1024)`). Without these decisions the encryption service, its configuration and the endpoints that use it cannot be implemented or tested.

## Expected Behavior

### Acceptance

- No encryption-related Pending decision is left in `email/smtp-connections.md` or
  `implement-email/encryption.md`; every item of the latter is marked resolved and links the
  permanent spec that holds the decision.
- `email/password-encryption.md` exists, is indexed from `email.md`, and records every
  "Decided" section below precisely enough to implement and test (round-trip, fresh nonce,
  tampering, wrong key, associated-data mismatch, unknown key id, malformed bytes, rotation).
- The edits listed in the Scope table are made, and nothing outside it changes.

### Backward compatibility

- No SMTP rows exist in production yet, so no stored password needs migrating.
- `SMTP_ENCRYPTION_KEY` becomes **required at boot**: the deploy docs must say it is added to
  the production env file **before** the first release that validates it, otherwise
  `bin/check-config` fails that release (which stops it before the swap).
- `.env` gains the development key, so local and CI boots keep working.

## Solution

### Scope

#### In (spec and doc changes only)

- **New permanent detail file `docs/agents/specs/email/password-encryption.md`**, indexed
  from `email.md`: mechanism and rejected alternatives, key format and HKDF derivations, byte
  layout, associated data, create-flow ordering, rotation and `bin/reencrypt-smtp-passwords`
  (with `--check`), security rules and threat model, testable rules.
- **Edits:**

  | File | Change |
  | --- | --- |
  | `email/smtp-connections.md` | Security section links the new file; encryption Pending decision removed. |
  | `email/failures.md` | `409 SMTP_PASSWORD_UNREADABLE` row and fixed message. |
  | `architecture/configuration.md` | `SMTP_ENCRYPTION_KEY`, `PREVIOUS_SMTP_ENCRYPTION_KEYS`, their rules and development key; `bin/check-config` failure names; sodium check (check-config and web boot). |
  | `architecture/dependencies.md` | `sodium` among the required extensions. |
  | `organizations/data-model/smtp-connections.md` | `password VARBINARY(2048)`, migration `0009`. |
  | `api/endpoints/pidgey-admin/smtp-connections.md` | Create ordering (insert → encrypt → update, one transaction); test email may answer `409 SMTP_PASSWORD_UNREADABLE`; `hasPassword` stays `true` when unreadable. |
  | `docs/agents/deployment/server.md` / `pipeline.md` | New env variables, back the key up separately from the database, rotation runbook. |
  | `implement-email/encryption.md` | Every item marked resolved with links to the permanent specs; building block rewritten as the implementation work (cipher, keys, migration, CLI, config, composer). |

#### Out

- Any code: migration, `composer.json`, cipher class, CLI command, `ConfigValidator` — those
  are implementation issues created when #149 is split again.
- Transport behavior (#152): how the transport receives the plain password, timeouts, TLS.
- Endpoint hardening (#153).
- Test infrastructure (#154): fixtures, fake transport, Mailpit. This issue only lists the
  encryption's testable rules.
- Frontend changes: none needed (the UI shows `error.message` like any error).

### Decided: key format

- The configured encryption key follows the **same rules as `SECRET_KEY`**
  (`architecture/configuration.md#secret-keys`): the raw environment string, at least
  **32 bytes**, no `,`, generated from at least 32 CSPRNG bytes (e.g. `openssl rand -hex 32`),
  and the development key refused unless `PHP_ENV` is `development` or `test`.
- The 32-byte cipher key is **derived**, never the raw string itself:
  `hash_hkdf('sha256', $raw, 32, 'pidgey:smtp-password:v1')`.
  - The `info` string gives domain separation (the derived key cannot collide with any other
    use of the same raw string) and carries a scheme version (`v1`) for a future change.
- Rejected: a strict 64-hex-char key (`sodium_hex2bin`) or a 43-char base64url key — they
  would give operators a second, different key rule set.
- Still open in other topics: dedicated key vs `SECRET_KEY` as the source, and a key id
  (possibly derived from the key, e.g. a separate `hash_hkdf` with `info`
  `pidgey:smtp-key-id`) for rotation.

### Decided: create-flow ordering and associated data

- **Create:** in the create endpoint's existing transaction, `INSERT` the row with
  `password = NULL`, read the new id (`lastInsertId`), encrypt with the associated data built
  from it, then `UPDATE smtp_connections SET password = ? WHERE id = ?`. Any failure rolls the
  whole create back; a row without its password is never visible.
  - A connection created without credentials skips the encrypt/update step (`NULL` stays
    `NULL`).
- **Update:** the id and organization never change, so no special ordering is needed. A
  new `password` is encrypted with the same associated data; when no password is sent, the
  stored bytes are kept as is (no re-encryption).
- **Associated data (canonical):** `pidgey:smtp-connection:v1:<organization_id>:<connection_id>`
  — decimal ids, `:` separator — rebuilt identically on every decrypt. It is not stored.
- Rejected:
  - reserving the id first (would need UUIDs or a sequence table);
  - binding organization id plus a random per-row value (an extra column for no gain);
  - binding only the organization id (a ciphertext copied between connections of the same
    organization would still decrypt).
- **Known limit (documented, not defended):** the associated data does not include `host`,
  `port` or `username`. An attacker with database write access could repoint a row to their
  own host and receive the password on the next send; binding those fields would force a
  re-encryption on every host edit, and database write access already allows worse.

### Decided: performance and security

#### Performance

- Encrypt/decrypt cost is microseconds; it runs on create/update (only when a password is
  sent) and once per send.
- The HKDF derivation runs once per process, when the container builds the cipher, and the
  derived key is kept in that object — never derived per call.
- List/show never decrypt: `hasPassword` is `password IS NOT NULL`.

#### Security rules

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
   uses it — documented as hygiene, not a guarantee (PHP copies strings).
5. **No custom comparisons:** authentication-tag verification is libsodium's
   (`..._decrypt` returning `false`); no hand-written constant-time code.
6. **Key custody:** the key lives only in the environment (the production env file on the
   host), never in the database; dumps and backups hold only ciphertext. Losing the key means
   every stored password must be re-entered, so the deploy docs must say to back the key up
   separately from the database.
7. **Threat model (stated in the spec):**
   - Protected: database leaks (dump, backup, read-only SQL injection) and ciphertext copied
     across rows or organizations.
   - Not protected: an attacker with database write access repointing `host` (see the
     create-flow section), or with access to the environment or the process.

### Decided: undecryptable stored password

- AEAD cannot tell a wrong/removed key, corrupt bytes and an associated-data mismatch apart
  (all fail the tag check); malformed bytes (shorter than nonce + tag) and an unknown key id
  (if rotation adopts one) are detectable. **All of them get the same answer.**
- **Answer:** a new error code **`409 SMTP_PASSWORD_UNREADABLE`**, added to
  `email/failures.md`, with the fixed `error.message`
  `The stored SMTP password could not be decrypted; set it again`. The SMTP server is
  **never contacted**. Applies to the test email now and `POST /v1/messages` later.
  - 409: the resource's state blocks the action and the client can fix it.
  - Rejected: `500 INTERNAL_ERROR` (not actionable) and `502 SMTP_AUTH_FAILED` without
    contacting the server (blames the server for a local key problem).
- **Logging:** one operator log line per failure, `smtp-password-decrypt-failed`, at error
  level, with the connection id and organization id (and the key id tried, if rotation
  adopts key ids). Never the stored bytes, the key or any plain text.
- **`hasPassword` stays `true`:** bytes are still stored; "no password" and "broken
  password" must stay distinguishable.
- **Recovery:** `PATCH` with a new `password` overwrites the bytes, encrypted with the
  current key; clearing `username` and `password` sets the column to `NULL`.
- **Detection only at send time:** list and show never decrypt. A command that checks every
  row belongs to the key-rotation topic.
- **Frontend:** shows the `error.message` on the test-email action like any other error; no
  special UI in v1.

### Decided: `ext-sodium` as a dependency

1. **Declared:** `"ext-sodium": "*"` is added to `require` in `source/composer.json`, so
   `composer install` / `composer check-platform-reqs` fail on a PHP without it (the lock
   file only gets a new content hash).
2. **Spec:** `architecture/dependencies.md` lists the required PHP extensions as `pdo_mysql`
   and `sodium` — updated by this issue, as the policy requires before a new runtime
   dependency.
3. **`bin/check-config`:** checks `extension_loaded('sodium')`; on failure it reports
   `PHP extension: sodium missing` (name only, like the other checks).
4. **Web boot:** the same check runs at web boot, refusing through the invalid-configuration
   path, consistent with "invalid configuration refuses boot". (Unlike the PHP version
   check, which runs in `bin/check-config` only.)
5. **Images:** the official `php:8.4` images compile `sodium` in, so no dev Dockerfile change
   is expected. The plan must confirm `sodium` is present wherever PHPUnit runs in CI (the
   CircleCI image has a different base) and add it if not. The production host is covered by
   `bin/check-config`, which runs before every release.

### Decided: column size and byte layout

- **Stored byte layout:**

  ```text
  version (1) || key_id (8) || nonce (24) || ciphertext (n) || tag (16)
  ```

  - `version` is the format version (`0x01`); `key_id` is reserved now and defined by the
    key-rotation decision.
  - Overhead is 49 bytes; the largest password (255 characters, up to 1020 UTF-8 bytes)
    needs 1069 bytes.
  - On decrypt, stored bytes shorter than 50 (49 + a non-empty password) or with an unknown
    `version` are malformed → `409 SMTP_PASSWORD_UNREADABLE`.
- **Column:** a new migration `0009_widen_smtp_connections_password.sql` runs
  `ALTER TABLE smtp_connections MODIFY password VARBINARY(2048) NULL` (merged migrations are
  never edited — `persistence/schema-migrations.md`). 2048 leaves margin for future format
  changes; `organizations/data-model/smtp-connections.md` is updated to `VARBINARY(2048)`.
  No SMTP rows exist in production yet, so the `ALTER` is immediate.
- The password field rule stays "up to 255 characters".
- Rejected: a byte limit on the password (an odd API/UI rule) and dropping the version/key id
  (still 1060 bytes, and loses rotation).

### Decided: key source and rotation

#### Key source

- A **dedicated key, `SMTP_ENCRYPTION_KEY`**, not derived from `SECRET_KEY`: JWT keys rotate
  cheaply, SMTP keys force re-encrypting every stored password, so their rotations stay
  independent.
- Same rules as `SECRET_KEY` (see "key format"): missing or shorter than 32 bytes refuses
  boot; no `,`; never logged.
- **Development key:** `pidgey-development-only-insecure-smtp-key-0000000` (≥32 bytes, no
  `,`), in the root `.env` and in `ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY`;
  recognized by exact byte comparison and refused unless `PHP_ENV` is `development` or `test`.
- Boot is refused when `SMTP_ENCRYPTION_KEY` is byte-equal to `SECRET_KEY`.

#### Rotation

1. **Previous keys:** `PREVIOUS_SMTP_ENCRYPTION_KEYS`, comma-separated, parsed like
   `PREVIOUS_SECRET_KEYS` (trimmed entries, empty entry refuses boot, each entry follows the
   key rules, development key refused outside `development`/`test`). Used **only to
   decrypt**; encryption always uses the current key.
2. **Key id:** 8 bytes derived from the key, `hash_hkdf('sha256', $raw, 8,
   'pidgey:smtp-key-id:v1')`; no manually assigned ids. Boot is refused when two configured
   keys share an id (a duplicated key). On decrypt the key is looked up by id; an unknown id
   → `409 SMTP_PASSWORD_UNREADABLE`, and the log line names the id tried.
3. **Lazy re-encryption:** every write of a new `password` uses the current key. No
   re-encryption in the send path (it would make sends write to the database).
4. **CLI `bin/reencrypt-smtp-passwords`:** re-encrypts every row whose key id is not the
   current key's, one transaction per row, with that row's associated data; idempotent; runs
   with the normal runtime database user; does not change `updated_at`.
   - `--check`: dry run — decrypts every row, changes nothing.
   - Output: counts of re-encrypted (or due, with `--check`), already current, and unreadable
     rows, plus the unreadable ids; never any password data.
   - Exit `0` when nothing is unreadable, `1` otherwise.
5. **Runbook** (added to the deployment docs): generate a new key → move the old one to
   `PREVIOUS_SMTP_ENCRYPTION_KEYS` and set the new one as `SMTP_ENCRYPTION_KEY` → deploy →
   run `bin/reencrypt-smtp-passwords` → once `--check` reports no old-key rows, remove the
   old key. The deploy does **not** run the command automatically.
6. **Deploy variables:** both keys go in the production env file on the host; CircleCI and
   deploy docs list them alongside `SECRET_KEY`; `.env` gets the development value.
7. **`bin/check-config` failure names** follow the existing pattern:
   `SMTP_ENCRYPTION_KEY: missing`, `SMTP_ENCRYPTION_KEY: too short`,
   `SMTP_ENCRYPTION_KEY: development key`, `SMTP_ENCRYPTION_KEY: same as SECRET_KEY`,
   `PREVIOUS_SMTP_ENCRYPTION_KEYS: empty entry`, `PREVIOUS_SMTP_ENCRYPTION_KEYS: too short`,
   `PREVIOUS_SMTP_ENCRYPTION_KEYS: development key`,
   `PREVIOUS_SMTP_ENCRYPTION_KEYS: duplicate key`.

### Decided: mechanism and alternatives

- **Mechanism:** libsodium AEAD **XChaCha20-Poly1305**
  (`sodium_crypto_aead_xchacha20poly1305_ietf_encrypt` / `_decrypt`), with the associated
  data, nonce and layout decided above. It is the only candidate with native associated
  data, a nonce safe to pick at random, no hardware dependency, and no extension beyond
  `sodium`.
- **Rejected alternatives** (recorded in the spec with these reasons):
  - `sodium_crypto_secretbox` (XSalsa20-Poly1305): no associated data; row binding would need
    hand-built crypto.
  - OpenSSL AES-256-GCM: 12-byte nonce (thin random-nonce margin), needs `ext-openssl`, an
    API easier to misuse (tag length, options).
  - libsodium AES-256-GCM: only with hardware AES support; may be missing on the shared
    production host.
  - `sodium_crypto_secretstream`: built for streams; overkill for one short password.
  - External KMS / vault: unavailable on the shared host and adds a network dependency to
    every send; out of scope for v1.
- The pending decision in `email/smtp-connections.md` (with its `e.g.
  sodium_crypto_secretbox` hint) is replaced by this decision.

### Decided: edge cases

**Write path**

1. **Whitespace / unusual characters:** never trimmed or normalized; encrypted as its exact
   UTF-8 bytes (NUL and control characters included), within the existing field rules.
2. **Same password sent again:** always re-encrypted with a fresh nonce; no "unchanged"
   check (it would need a decrypt on the write path).
3. **`username` / `host` / `port` changed without a password:** stored bytes kept; still
   valid, since the associated data holds only the ids.
4. **Clearing credentials:** empty `username` and `password` → column `NULL`; an empty string
   is never encrypted; the "both set or both empty" rule is unchanged.
5. **Encryption failure on create/update** (sodium or `random_bytes` error): transaction
   rolled back, `500 INTERNAL_ERROR`, real error logged without any password data.
6. **Moving a connection to another organization:** no endpoint allows it; the spec notes
   that any future move must decrypt and re-encrypt (the organization id is in the
   associated data).

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

### Testing

None for this issue: spec files are not checked by tests; the change is verified by PR review.

## Benefits

- Stored SMTP passwords are protected against database leaks and cannot be replayed across rows or organizations.
- Key rotation is possible without downtime and independent of JWT key rotation.
- A broken key or ciphertext gives an actionable error instead of an opaque 500.
- The implementation issues of #149 get a precise, testable contract.
