# Key set and cipher

Implement the encryption per `docs/agents/specs/email/password-encryption.md` (Mechanism,
Keys, Byte layout, Associated data, Security rules).

- `Pidgey\Crypto\EncryptionKeys`: current key + previous keys from the environment
  (`fromEnv()`, as `SecretKeys`). Derives once, at construction, each cipher key
  (`hash_hkdf('sha256', $raw, 32, 'pidgey:smtp-password:v1')`) and key id
  (`hash_hkdf('sha256', $raw, 8, 'pidgey:smtp-key-id:v1')`). Exposes the current key id and
  a lookup by key id. `#[SensitiveParameter]` and `__debugInfo` redaction.
- `Pidgey\Crypto\PasswordCipher`:
  - `encrypt(password, organizationId, connectionId)`: refuses an empty password; fresh
    24-byte nonce from `random_bytes`; `sodium_crypto_aead_xchacha20poly1305_ietf_encrypt`
    with the associated data `pidgey:smtp-connection:v1:<org>:<id>`; returns
    `0x01 || key_id || nonce || ciphertext+tag`.
  - `decrypt(stored, organizationId, connectionId)`: malformed (< 50 bytes, unknown version),
    unknown key id and tag failure all throw `DecryptionFailed`.
- `Pidgey\Crypto\DecryptionFailed`: fixed message, carries only the key id tried (hex or
  null), never bytes, keys or plain text.
- `Pidgey\Container::passwordCipher()` (and the key set), built once per container.

Tests (unit): every testable rule of `password-encryption.md` — round-trip (including
control characters and a 255-character 4-byte UTF-8 password), fresh nonce, tampering with
each byte, wrong key, organization / connection mismatch, unknown key id, short bytes,
unknown version, previous-key decrypt; exception messages and `var_dump`/`print_r` of the
key set and cipher contain no key or password.

## Files to Change

- `source/lib/Crypto/EncryptionKeys.php` — new.
- `source/lib/Crypto/PasswordCipher.php` — new.
- `source/lib/Crypto/DecryptionFailed.php` — new.
- `source/lib/Container.php` — `passwordCipher()`.
- `source/tests/unit/Crypto/*Test.php` — new.
- `source/tests/unit/ContainerTest.php` / `tests/integration/ContainerTest.php` — container wiring.
