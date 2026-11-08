<?php

declare(strict_types=1);

namespace Pidgey\Crypto;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Encrypts the SMTP connection passwords at rest with XChaCha20-Poly1305 (see
 * `email/password-encryption.md`).
 *
 * Stored bytes: `0x01 || key_id (8) || nonce (24) || ciphertext || tag (16)`, bound to the row by
 * the associated data `pidgey:smtp-connection:v1:<organization_id>:<connection_id>`.
 */
final readonly class PasswordCipher
{
    /** Format version byte of the stored bytes. */
    public const VERSION = "\x01";

    private const NONCE_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
    private const TAG_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;
    private const HEADER_BYTES = 1 + EncryptionKeys::KEY_ID_BYTES + self::NONCE_BYTES;

    /** Smallest valid stored value: the overhead plus a one-byte password. */
    private const MIN_STORED_BYTES = self::HEADER_BYTES + self::TAG_BYTES + 1;

    /**
     * Builds the cipher.
     *
     * @param EncryptionKeys $keys The current and previous keys.
     */
    public function __construct(private EncryptionKeys $keys)
    {
    }

    /**
     * Encrypts a password with the current key and a fresh nonce.
     *
     * @param string  $password       The plain password, exact bytes, non-empty.
     * @param integer $organizationId Id of the connection's organization.
     * @param integer $connectionId   Id of the connection.
     *
     * @return string The stored bytes.
     *
     * @throws InvalidArgumentException When the password is empty.
     */
    public function encrypt(#[SensitiveParameter] string $password, int $organizationId, int $connectionId): string
    {
        if ($password === '') {
            throw new InvalidArgumentException('An empty password is never encrypted');
        }

        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $password,
            self::associatedData($organizationId, $connectionId),
            $nonce,
            $this->keys->currentCipherKey(),
        );

        return self::VERSION . $this->keys->currentKeyId() . $nonce . $ciphertext;
    }

    /**
     * Decrypts stored bytes with the key their key id names.
     *
     * @param string  $stored         The stored bytes.
     * @param integer $organizationId Id of the connection's organization.
     * @param integer $connectionId   Id of the connection.
     *
     * @return string The plain password.
     *
     * @throws DecryptionFailed When the bytes are malformed, the key id is unknown or the
     *                          authentication fails.
     */
    public function decrypt(#[SensitiveParameter] string $stored, int $organizationId, int $connectionId): string
    {
        if (strlen($stored) < self::MIN_STORED_BYTES || $stored[0] !== self::VERSION) {
            throw new DecryptionFailed();
        }

        $keyId = substr($stored, 1, EncryptionKeys::KEY_ID_BYTES);
        $key = $this->keys->cipherKey($keyId);

        if ($key === null) {
            throw new DecryptionFailed(bin2hex($keyId));
        }

        $password = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($stored, self::HEADER_BYTES),
            self::associatedData($organizationId, $connectionId),
            substr($stored, 1 + EncryptionKeys::KEY_ID_BYTES, self::NONCE_BYTES),
            $key,
        );

        if ($password === false) {
            throw new DecryptionFailed(bin2hex($keyId));
        }

        return $password;
    }

    /**
     * Tells whether stored bytes were encrypted with the current key (by their version byte and
     * key id), so that a key rotation can skip them; does not authenticate the bytes. Bytes with
     * an unknown version are never current.
     *
     * @param string $stored The stored bytes.
     *
     * @return boolean
     */
    public function isCurrent(string $stored): bool
    {
        return str_starts_with($stored, self::VERSION)
            && substr($stored, 1, EncryptionKeys::KEY_ID_BYTES) === $this->keys->currentKeyId();
    }

    /**
     * Hides the keys from var_dump() and similar dumps.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['keys' => '[redacted]'];
    }

    /**
     * Builds the associated data that binds the stored bytes to their row.
     *
     * @param integer $organizationId Id of the connection's organization.
     * @param integer $connectionId   Id of the connection.
     *
     * @return string
     */
    private static function associatedData(int $organizationId, int $connectionId): string
    {
        return "pidgey:smtp-connection:v1:{$organizationId}:{$connectionId}";
    }
}
