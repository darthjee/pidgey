# New `email/password-encryption.md`

Create the permanent detail file holding the whole encryption design, and index it.

Content, in this order (values verbatim from issue #151):

1. **Purpose** line.
2. **Mechanism:** XChaCha20-Poly1305 AEAD
   (`sodium_crypto_aead_xchacha20poly1305_ietf_encrypt` / `_decrypt`), and the rejected
   alternatives with their one-line reasons.
3. **Keys:** `SMTP_ENCRYPTION_KEY` (dedicated, not derived from `SECRET_KEY`) and
   `PREVIOUS_SMTP_ENCRYPTION_KEYS` (decrypt only); link
   `architecture/configuration.md` for their validation rules instead of repeating them.
   Derivations: cipher key `hash_hkdf('sha256', $raw, 32, 'pidgey:smtp-password:v1')`, key id
   `hash_hkdf('sha256', $raw, 8, 'pidgey:smtp-key-id:v1')`, computed once per process.
4. **Byte layout:** `version (1) || key_id (8) || nonce (24) || ciphertext (n) || tag (16)`,
   `version = 0x01`, fresh nonce from
   `random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES)`; malformed when shorter
   than 50 bytes or unknown version.
5. **Associated data:** `pidgey:smtp-connection:v1:<organization_id>:<connection_id>`,
   decimal ids, not stored.
6. **Write path:** create ordering (insert with `NULL` → `lastInsertId` → encrypt → update, one
   transaction), update keeps bytes when no password is sent, `NULL` never encrypted, never
   trimmed, always a fresh nonce; encryption failure → rollback, `500 INTERNAL_ERROR`.
7. **Read path:** decrypt only in the SMTP transport; key looked up by key id; any failure →
   `409 SMTP_PASSWORD_UNREADABLE` (link `failures.md`), server never contacted, the
   `smtp-password-decrypt-failed` log line (ids and key id only); `hasPassword` stays `true`;
   recovery by `PATCH`.
8. **Rotation:** lazy re-encryption on write; `bin/reencrypt-smtp-passwords` and `--check`
   (per-row transaction with `SELECT … FOR UPDATE`, re-check key id after the lock, skip
   current rows, unreadable rows skipped and reported, exit codes, output never shows password
   data, normal runtime DB user, `updated_at` unchanged); no automatic run on release; link
   the runbook in `deployment/operations.md`.
9. **Security rules** 1–7, with the threat model (protected / not protected, including the
   documented host-repointing limit).
10. **Edge cases** 1–15.
11. **Testable rules:** round-trip; two encryptions of the same password differ; tampering
    with any byte fails; wrong key fails; associated-data mismatch (other id or organization)
    fails; unknown key id and malformed bytes fail as `SMTP_PASSWORD_UNREADABLE`; decrypt with
    a previous key works; re-encryption command (re-encrypts, idempotent, `--check` changes
    nothing, unreadable row → exit 1); no password/key/bytes in logs or exceptions.

## Files to Change

- `docs/agents/specs/email/password-encryption.md` — new file.
- `docs/agents/specs/email.md` — add the row
  `| [password-encryption.md](email/password-encryption.md) | Encryption at rest of the SMTP password: mechanism, keys, rotation, failures. |`
  after `smtp-connections.md`.
