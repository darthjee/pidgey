<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Random\RandomException;

/**
 * Generates session ids: random UUID v4 values (see `api/authentication/sessions.md`).
 */
final class SessionUuid
{
    /**
     * Generates a random UUID v4 in its lowercase 8-4-4-4-12 form.
     *
     * @return string
     *
     * @throws RandomException When no source of randomness is available.
     */
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
