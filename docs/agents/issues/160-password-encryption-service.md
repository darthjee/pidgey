# Issue: Password encryption service

## Description

Part of epic #149 (email integration). Implements the encryption at rest of the stored SMTP
password, as settled by #151.

### Specs

- `docs/agents/specs/email/password-encryption.md` (mechanism, byte layout, associated data,
  keys, rotation, security rules, testable rules);
- `docs/agents/specs/architecture/configuration.md#smtp-encryption-keys` and
  `#bin-check-config`;
- `docs/agents/specs/organizations/data-model/smtp-connections.md#migrations`;
- implementation notes: `docs/agents/specs/implement-email/encryption.md`.

## Problem

Stored SMTP passwords must be encrypted at rest before any endpoint can accept one. Nothing in `source/lib` encrypts them today, and `smtp_connections.password` (`VARBINARY(1024)`) is too small for the decided byte layout.

## Expected Behavior

### In scope

1. **`Pidgey\Crypto\PasswordCipher`** (XChaCha20-Poly1305):
   - `encrypt(password, organizationId, connectionId)` → the stored bytes, with the current
     key;
   - `decrypt(bytes, organizationId, connectionId)` → the plain password string, or
     `Pidgey\Crypto\DecryptionFailed` (fixed message, no plain text, key or stored bytes;
     only the key id tried);
   - builds the associated data `pidgey:smtp-connection:v1:<organization_id>:<connection_id>`
     itself, so callers never assemble it;
   - `#[SensitiveParameter]` on every password parameter.
2. **`Pidgey\Crypto\EncryptionKeys`**: current and previous keys, derived cipher keys and key
   ids, built once per process by the container, following the `Pidgey\Auth\SecretKeys`
   precedent.
3. **Configuration**: the `SMTP_ENCRYPTION_KEY` / `PREVIOUS_SMTP_ENCRYPTION_KEYS` rules in
   `ConfigValidator`, `bin/check-config` and web boot; the `sodium` extension check;
   `ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY`.
4. **Migration** `0009_widen_smtp_connections_password.sql` (`password VARBINARY(2048)`).
5. **Rotation CLI** `bin/reencrypt-smtp-passwords` with `--check`, and the
   `SmtpConnectionRepository` methods it needs: list the rows with a password, lock one row
   (`SELECT … FOR UPDATE`), write the password without changing `updated_at`. Kept here so the
   key ships with its rotation tool (the runbook in `deployment/operations.md` already
   describes it).
6. **Environment**:
   - `"ext-sodium": "*"` in `source/composer.json`;
   - the development `SMTP_ENCRYPTION_KEY` in the root `.env` **and** in CircleCI
     `backend_tests`, since the app refuses to boot without it and this issue may land before
     the email test infrastructure issue;
   - a release note in the PR: the operator adds `SMTP_ENCRYPTION_KEY` to the server `.env`
     before merging (`deployment/operations.md`).
7. **Tests**: the testable rules of `email/password-encryption.md`, and the CLI against the
   test database.

### Out of scope

- **Encrypt on create / update** — the SMTP connection endpoints issue: insert with `NULL`,
  encrypt with the new id, update in the same transaction; the credential-redirection and
  clearing rules.
- **Decrypt at send time** — the send flow and test email endpoint issues: wrapping in
  `SecretString`, the `smtp-password-decrypt-failed` log line, `409 SMTP_PASSWORD_UNREADABLE`.
- **Logging** — nothing in this issue logs (the logger comes with the logger and clock
  foundation issue); the CLI prints counts and ids to stdout only, never password data.

### Edge cases

The 15 edge cases of `email/password-encryption.md#edge-cases` apply as specified. This issue
also covers:

1. **Configuration failures**: `ConfigValidator` / `bin/check-config` tests for every SMTP
   key failure name (missing, shorter than 32 bytes, the development key where it is refused,
   same as `SECRET_KEY` — current or previous —, duplicated key / key-id collision, empty
   `PREVIOUS_SMTP_ENCRYPTION_KEYS` entries). Each test also checks that no key value appears
   in the output.
2. **CLI re-check after the lock** (edge case 13): tested deterministically, not as a real
   race: a second PDO connection rewrites the row with the current key between the listing
   and the lock, and the CLI skips it without overwriting. The real concurrent-request tests
   belong to the SMTP connection endpoints issue (lock-holder script).
3. **Largest password**: 255 characters of 4-byte UTF-8 (1069 stored bytes) round-trips
   through the repository, proving migration `0009`.
4. **CLI trivial outcomes**: no rows, or only rows without a password → zero counts, exit
   `0`; the output never holds password data, unreadable ids included.
5. **Where the key is required**: `SMTP_ENCRYPTION_KEY` is required by the same entry points
   that already require `SECRET_KEY` (web boot, `bin/check-config`, and any CLI that
   validates the full configuration). The PR description lists which commands now need it,
   next to the release note.

## Solution

### Acceptance

- Every testable rule of `email/password-encryption.md` is covered.
- `bin/check-config` reports the new key failures by name.
- The matching items of `implement-email/encryption.md` are ticked in the same PR.

### Dependencies

None. Agents: backend, security.

### Notes

- `Pidgey\Infra\Clock` already exists; this issue does not need it, but the logger and clock foundation issue should reuse it rather than add one.
- The CLI follows the existing `Pidgey\Infra\Cli\*Command` pattern behind a `source/bin/` entry point.

## Benefits

- A database dump, backup or read-only SQL injection exposes only ciphertext bound to its row.
- Keys rotate without downtime, with a tool to finish the rotation and check it.
- The endpoints and the send flow get a small, tested contract and never touch key material.
