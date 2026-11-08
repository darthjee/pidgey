<?php

declare(strict_types=1);

namespace Pidgey\Crypto;

use LogicException;
use Pidgey\Infra\Env;
use SensitiveParameter;

/**
 * The SMTP password encryption keys: the current `SMTP_ENCRYPTION_KEY` encrypts and decrypts,
 * the retired `PREVIOUS_SMTP_ENCRYPTION_KEYS` only decrypt (see
 * `email/password-encryption.md#keys`).
 *
 * The cipher keys and key ids are derived once, at construction, never per call. The raw and
 * derived keys are never logged or returned outside the cipher; they are hidden from debug dumps
 * and the object refuses to be serialized or unserialized.
 */
final readonly class EncryptionKeys
{
    /**
     * Length of a derived cipher key, in bytes (`SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES`,
     * spelled out so that the boot validation can derive key ids without `sodium`).
     */
    public const CIPHER_KEY_BYTES = 32;

    /** Length of a derived key id, in bytes. */
    public const KEY_ID_BYTES = 8;

    private const CIPHER_KEY_INFO = 'pidgey:smtp-password:v1';
    private const KEY_ID_INFO = 'pidgey:smtp-key-id:v1';

    private string $currentKeyId;

    /** @var array<string, string> Derived cipher keys by binary key id, the current key first. */
    private array $cipherKeys;

    /**
     * Builds the key set and derives every cipher key and key id.
     *
     * When two keys share a key id (refused at boot), the first one wins.
     *
     * @param string   $current  The raw current key, which encrypts.
     * @param string[] $previous The raw retired keys, used only to decrypt.
     */
    public function __construct(
        #[SensitiveParameter] string $current,
        #[SensitiveParameter] array $previous = [],
    ) {
        $cipherKeys = [];

        foreach ([$current, ...$previous] as $raw) {
            $cipherKeys[self::keyIdOf($raw)] ??= self::cipherKeyOf($raw);
        }

        $this->currentKeyId = self::keyIdOf($current);
        $this->cipherKeys = $cipherKeys;
    }

    /**
     * Reads `SMTP_ENCRYPTION_KEY` and `PREVIOUS_SMTP_ENCRYPTION_KEYS` from the environment.
     *
     * Entries of `PREVIOUS_SMTP_ENCRYPTION_KEYS` are trimmed and empty entries ignored; the boot
     * validation has already refused invalid values.
     *
     * @return self
     *
     * @throws \RuntimeException When `SMTP_ENCRYPTION_KEY` is missing.
     */
    public static function fromEnv(): self
    {
        $previous = array_map('trim', explode(',', (string) Env::get('PREVIOUS_SMTP_ENCRYPTION_KEYS', '')));

        return new self(
            Env::required('SMTP_ENCRYPTION_KEY'),
            array_values(array_filter($previous, static fn (string $key): bool => $key !== '')),
        );
    }

    /**
     * Derives the key id of a raw key: `hash_hkdf('sha256', $raw, 8, 'pidgey:smtp-key-id:v1')`.
     *
     * @param string $raw The raw, non-empty key.
     *
     * @return string Binary key id (8 bytes).
     */
    public static function keyIdOf(#[SensitiveParameter] string $raw): string
    {
        return hash_hkdf('sha256', $raw, self::KEY_ID_BYTES, self::KEY_ID_INFO);
    }

    /**
     * Returns the key id of the current key.
     *
     * @return string Binary key id (8 bytes).
     */
    public function currentKeyId(): string
    {
        return $this->currentKeyId;
    }

    /**
     * Returns the cipher key derived from the current key.
     *
     * @return string Binary cipher key (32 bytes).
     */
    public function currentCipherKey(): string
    {
        return $this->cipherKeys[$this->currentKeyId];
    }

    /**
     * Looks a cipher key up by key id, among the current and previous keys.
     *
     * @param string $keyId Binary key id.
     *
     * @return string|null The binary cipher key, or null when no configured key has this id.
     */
    public function cipherKey(string $keyId): ?string
    {
        return $this->cipherKeys[$keyId] ?? null;
    }

    /**
     * Refuses to serialize, so that the derived keys never leave the process.
     *
     * @return never
     *
     * @throws LogicException Always.
     */
    public function __serialize(): array
    {
        throw new LogicException('SMTP encryption keys cannot be serialized');
    }

    /**
     * Refuses to unserialize, so that keys are only ever built from the environment.
     *
     * @param array<string, mixed> $data Ignored.
     *
     * @return void
     *
     * @throws LogicException Always.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __unserialize(array $data): void
    {
        throw new LogicException('SMTP encryption keys cannot be unserialized');
    }

    /**
     * Hides the keys from var_dump() and similar dumps.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['currentKeyId' => '[redacted]', 'cipherKeys' => '[redacted]'];
    }

    /**
     * Derives the cipher key of a raw key: `hash_hkdf('sha256', $raw, 32, 'pidgey:smtp-password:v1')`.
     *
     * @param string $raw The raw, non-empty key.
     *
     * @return string Binary cipher key (32 bytes).
     */
    private static function cipherKeyOf(#[SensitiveParameter] string $raw): string
    {
        return hash_hkdf('sha256', $raw, self::CIPHER_KEY_BYTES, self::CIPHER_KEY_INFO);
    }
}
