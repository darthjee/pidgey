<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Model\RefreshToken;
use Pidgey\Model\User;

/**
 * An active refresh token (not revoked, not expired) with its user, as found by a passive
 * lookup (session status). Holds the token's digest only, never the plain token.
 */
final readonly class ActiveSession
{
    /**
     * Builds the value.
     *
     * @param RefreshToken $token The active refresh token.
     * @param User         $user  The token's user.
     */
    public function __construct(
        public RefreshToken $token,
        public User $user,
    ) {
    }
}
