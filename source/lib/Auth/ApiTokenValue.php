<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Random\RandomException;
use SensitiveParameter;

/**
 * Generates plain API tokens and computes the digest that is stored instead of them.
 */
final class ApiTokenValue
{
    public const PREFIX = 'pdg_';

    private const BYTES = 32;

    /**
     * Generates a new plain API token: the `pdg_` prefix followed by 32 random bytes, hex-encoded.
     *
     * @return string
     *
     * @throws RandomException When no source of randomness is available.
     */
    public static function generate(): string
    {
        return self::PREFIX . bin2hex(random_bytes(self::BYTES));
    }

    /**
     * Computes the SHA-256 hex digest of a plain API token, the only form that is stored.
     *
     * @param string $plain The plain API token.
     *
     * @return string 64 lowercase hex characters.
     */
    public static function hash(#[SensitiveParameter] string $plain): string
    {
        return hash('sha256', $plain);
    }
}
