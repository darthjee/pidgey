<?php

declare(strict_types=1);

namespace Pidgey\Crypto;

use RuntimeException;

/**
 * A stored SMTP password could not be decrypted: wrong or removed key, corrupt bytes,
 * associated-data mismatch, malformed bytes, unknown version or unknown key id.
 *
 * The message is fixed; the exception carries only the key id tried, never the stored bytes,
 * a key or any plain text.
 */
final class DecryptionFailed extends RuntimeException
{
    public const MESSAGE = 'The stored SMTP password could not be decrypted';

    /**
     * Builds the failure.
     *
     * @param string|null $keyId Hex key id read from the stored bytes, or null when the bytes are
     *                           malformed and carry none.
     */
    public function __construct(public readonly ?string $keyId = null)
    {
        parent::__construct(self::MESSAGE);
    }
}
