<?php

declare(strict_types=1);

namespace Pidgey\Auth;

/**
 * Base64url without padding (RFC 4648 section 5), with a strict and canonical decoder.
 */
final class Base64Url
{
    private const ALPHABET_PATTERN = '/\A[A-Za-z0-9_-]*\z/';

    /**
     * Encodes bytes with the URL-safe alphabet and no padding.
     *
     * @param string $bytes Raw bytes.
     *
     * @return string
     */
    public static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * Decodes a strict, canonical base64url string.
     *
     * Only the URL-safe alphabet is accepted, without padding, and re-encoding the result must
     * give back the same string, so no alternative encoding of the same bytes is accepted.
     *
     * @param string $encoded The base64url string.
     *
     * @return string|null The raw bytes, or null when the input is not strict canonical base64url.
     */
    public static function decode(string $encoded): ?string
    {
        if (preg_match(self::ALPHABET_PATTERN, $encoded) !== 1 || strlen($encoded) % 4 === 1) {
            return null;
        }

        $bytes = base64_decode(strtr($encoded, '-_', '+/'), true);

        if ($bytes === false || self::encode($bytes) !== $encoded) {
            return null;
        }

        return $bytes;
    }
}
