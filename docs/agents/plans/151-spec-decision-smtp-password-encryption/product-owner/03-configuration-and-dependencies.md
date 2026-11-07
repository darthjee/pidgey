# Configuration and dependencies

Add the two keys and the sodium check to the configuration spec, and the extension to the
dependency policy.

- `architecture/configuration.md`:
  - Settings table: `SMTP_ENCRYPTION_KEY` (required) and `PREVIOUS_SMTP_ENCRYPTION_KEYS`
    (optional, comma-separated, decrypt only), linking `email/password-encryption.md`.
  - A subsection under "Secret keys" (e.g. "SMTP encryption keys"): same rules as `SECRET_KEY`
    / `PREVIOUS_SECRET_KEYS` (length, `,`, trimming, empty entry); boot refused when any SMTP
    key is byte-equal to `SECRET_KEY` or when two SMTP keys share a key id (duplicate).
  - Development key: `pidgey-development-only-insecure-smtp-key-0000000` (49 bytes), in the
    root `.env` and `ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY`, same recognition and
    `PHP_ENV` rule as the `SECRET_KEY` development key.
  - `bin/check-config` output: the failure names `SMTP_ENCRYPTION_KEY: missing | too short |
    development key | same as SECRET_KEY`, `PREVIOUS_SMTP_ENCRYPTION_KEYS: empty entry | too
    short | development key | same as SECRET_KEY | duplicate key`, and
    `PHP extension: sodium missing`.
  - The sodium check (`extension_loaded('sodium')`) runs in `bin/check-config` **and** at web
    boot (unlike the PHP version check, which is check-config only).
- `architecture/dependencies.md`: required PHP extensions become `pdo_mysql` and `sodium`;
  `ext-sodium: *` is declared in `require` of `source/composer.json` (by the implementation
  issue). Note that the official `php:8.4` images include sodium, and that the CircleCI image
  must be confirmed to have it.

## Files to Change

- `docs/agents/specs/architecture/configuration.md` — keys, rules, development key, failure
  names, sodium check.
- `docs/agents/specs/architecture/dependencies.md` — `sodium` extension.
