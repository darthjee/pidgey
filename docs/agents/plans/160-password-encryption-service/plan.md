# Plan: Password encryption service

Issue: [160-password-encryption-service.md](../../issues/160-password-encryption-service.md)

## Overview

Add encryption at rest for stored SMTP passwords, as settled by #151: a
`Pidgey\Crypto\PasswordCipher` (XChaCha20-Poly1305, row-bound associated data, key id in the
stored bytes), its key set `Pidgey\Crypto\EncryptionKeys`, the `SMTP_ENCRYPTION_KEY` /
`PREVIOUS_SMTP_ENCRYPTION_KEYS` boot rules and the `sodium` check, migration `0009`, and the
rotation CLI `bin/reencrypt-smtp-passwords`. Nothing calls the cipher from an endpoint yet
(#164, #165, #166). The development key is set wherever the app boots in development and
tests (root `.env`, CircleCI), so CI keeps passing once boot requires it.

## Agents involved

- [backend](backend.md)
- [infra](infra.md)
- [product-owner](product-owner.md)

## Shared contracts

- **Development key** (exact value, 49 bytes, no `,`):
  `pidgey-development-only-insecure-smtp-key-0000000`.
  - backend: `ConfigValidator::DEVELOPMENT_SMTP_ENCRYPTION_KEY` holds exactly this value.
  - infra: root `.env` line `SMTP_ENCRYPTION_KEY=pidgey-development-only-insecure-smtp-key-0000000`,
    and the same `SMTP_ENCRYPTION_KEY` value in the CircleCI `backend_tests` primary container
    environment (next to `SECRET_KEY`).
- **Env keys**: `SMTP_ENCRYPTION_KEY` (required), `PREVIOUS_SMTP_ENCRYPTION_KEYS` (optional,
  comma-separated). Validated at web boot and by `bin/check-config`; `bin/migrate` does not
  validate the configuration, so it does not need the key.
- **Failure names** (from `architecture/configuration.md#smtp-encryption-keys`):
  `SMTP_ENCRYPTION_KEY: missing | too short | development key | same as SECRET_KEY`;
  `PREVIOUS_SMTP_ENCRYPTION_KEYS: empty entry | too short | development key | same as SECRET_KEY | duplicate key`;
  `PHP extension: sodium missing`.
- **Cipher contract** (consumed later by #164–#166):
  - `PasswordCipher::encrypt(string $password, int $organizationId, int $connectionId): string`
  - `PasswordCipher::decrypt(string $stored, int $organizationId, int $connectionId): string`,
    throws `Pidgey\Crypto\DecryptionFailed` (fixed message; exposes only the key id tried).
  - Stored bytes: `0x01 || key_id(8) || nonce(24) || ciphertext || tag(16)`.
  - Associated data: `pidgey:smtp-connection:v1:<organization_id>:<connection_id>`.
- **Release step**: the operator adds `SMTP_ENCRYPTION_KEY` to the server `.env` before this
  PR is merged (`deployment/operations.md`); the PR description says so and names the entry
  points that now need it (web boot, `bin/check-config`).
