<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Random\RandomException;
use SensitiveParameter;

/**
 * Generates plain refresh tokens and computes the digest that is stored instead of them
 * (see `api/authentication/sessions.md`).
 */
final class RefreshTokenValue
{
    private const BYTES = 48;

    /**
     * Generates a new plain refresh token: 48 random bytes, hex-encoded (96 characters).
     *
     * @return string
     *
     * @throws RandomException When no source of randomness is available.
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(self::BYTES));
    }

    /**
     * Computes the SHA-256 hex digest of a plain refresh token, the only form that is stored.
     *
     * @param string $plain The plain refresh token.
     *
     * @return string 64 lowercase hex characters.
     */
    public static function hash(#[SensitiveParameter] string $plain): string
    {
        return hash('sha256', $plain);
    }
}
