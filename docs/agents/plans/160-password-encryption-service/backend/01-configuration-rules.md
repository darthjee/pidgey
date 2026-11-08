# Configuration rules and sodium check

Add the SMTP encryption key rules to `Pidgey\Infra\ConfigValidator`, mirroring the existing
`SECRET_KEY` / `PREVIOUS_SECRET_KEYS` checks, per
`docs/agents/specs/architecture/configuration.md#smtp-encryption-keys`:

- `SMTP_ENCRYPTION_KEY`: missing, too short (< 32 bytes), development key outside
  `PHP_ENV` development/test, byte-equal to `SECRET_KEY`.
- `PREVIOUS_SMTP_ENCRYPTION_KEYS`: split on `,`, trimmed; empty entry, too short,
  development key, same as `SECRET_KEY`, and `duplicate key` when two configured SMTP keys
  (current + previous) derive the same key id
  (`hash_hkdf('sha256', $raw, 8, 'pidgey:smtp-key-id:v1')`). Each failure name reported at
  most once.
- Constant `DEVELOPMENT_SMTP_ENCRYPTION_KEY`.
- The `sodium` extension check (`PHP extension: sodium missing`), run both by
  `bin/check-config` and at web boot (it lives in `validate()`, unlike the PHP version check).
- `"ext-sodium": "*"` in `require` of `source/composer.json` (update `composer.lock`).

Tests: one per failure name, each asserting that the key value never appears in the output;
the valid development key passes in `test`; the `bin/check-config` integration test covers
the new names. Add `SMTP_ENCRYPTION_KEY` to any test env fixture or test setup that builds a
valid configuration.

## Files to Change

- `source/lib/Infra/ConfigValidator.php` — new checks and constant.
- `source/lib/Infra/Cli/CheckConfigCommand.php` — only if the sodium check needs wiring there.
- `source/composer.json`, `source/composer.lock` — `ext-sodium`.
- `source/tests/unit/...ConfigValidator*Test.php` (existing location) — new cases.
- `source/tests/integration/Infra/CheckConfigTest.php` — new failure names.
