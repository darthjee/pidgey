# Rotation CLI

Add `bin/reencrypt-smtp-passwords` with `--check`, per
`docs/agents/specs/email/password-encryption.md#rotation`, following the
`Pidgey\Infra\Cli\*Command` pattern (loads `config/.env`, testable with injected streams).

- Repository methods on `SmtpConnectionRepository`: list ids (with organization id) of rows
  whose `password IS NOT NULL`; lock one row (`SELECT … FOR UPDATE`) returning its stored
  bytes; write the password bytes without changing `updated_at`.
- Per row, one transaction: lock, re-check the key id after the lock (skip if already
  current), decrypt with the row's associated data, re-encrypt with the current key, write.
  An unreadable row is skipped and reported; processing continues.
- `--check`: decrypts every row, writes nothing; counts rows due.
- Output: counts of re-encrypted (or due), already current, unreadable, and the unreadable
  ids; never password data. Exit `0` when nothing is unreadable, `1` otherwise.
- Runs with the runtime database user.

Integration tests (test database): old-key rows re-encrypted, idempotent second run,
`--check` changes nothing, unreadable row → reported, exit `1`; no rows / only rows without a
password → zero counts, exit `0`; the re-check after the lock, deterministic: a second PDO
connection rewrites the row with the current key between the listing and the lock, and the
command skips it without overwriting; `updated_at` unchanged; output never contains a
password.

## Files to Change

- `source/bin/reencrypt-smtp-passwords` — new entry point.
- `source/lib/Crypto/Cli/ReencryptSmtpPasswordsCommand.php` (or `Pidgey\Infra\Cli\...`, matching the existing layout) — new.
- `source/lib/Repository/SmtpConnectionRepository.php` — rotation methods.
- `source/tests/integration/...ReencryptSmtpPasswords*Test.php` — new.
