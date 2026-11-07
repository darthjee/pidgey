# Resolve `implement-email/encryption.md`

Turn the temporary file from open decisions into the implementation work.

- Mark every open item resolved (`- [x]`), each with a one-line outcome and a link to the
  permanent spec that holds it (`email/password-encryption.md`,
  `architecture/configuration.md`, `architecture/dependencies.md`,
  `organizations/data-model/smtp-connections.md`, `email/failures.md`).
- Rewrite "Building block: password encryption service" as the implementation work, linking
  instead of restating:
  - the cipher (`Pidgey\Crypto\PasswordCipher` proposal), the key set
    (`Pidgey\Crypto\EncryptionKeys`) and `Pidgey\Crypto\DecryptionFailed`;
  - `ConfigValidator` / `bin/check-config` rules and the sodium check;
  - migration `0009`;
  - `bin/reencrypt-smtp-passwords`;
  - `ext-sodium` in `composer.json`, the development key in the root `.env`.
- Update the "Shared contract" and "New configuration keys" tables to the decided values
  (stored bytes layout, associated data, keys no longer "proposal").
- `implement-email.md`: mark #151 as settled where the index tracks the spec issues; leave
  the rest untouched.

## Files to Change

- `docs/agents/specs/implement-email/encryption.md` — items resolved, building block rewritten.
- `docs/agents/specs/implement-email.md` — #151 status only.
