<?php

declare(strict_types=1);

namespace Pidgey\Auth;

/**
 * The trusted claims of a verified access token.
 *
 * `username` and `isAdmin` are not part of it: they are informational only and never trusted.
 */
final readonly class AccessTokenClaims
{
    /**
     * Builds the claims.
     *
     * @param integer $userId    The `sub` claim: the user id.
     * @param string  $sessionId The `sid` claim: the session UUID.
     * @param integer $issuedAt  The `iat` claim, Unix seconds.
     * @param integer $expiresAt The `exp` claim, Unix seconds.
     */
    public function __construct(
        public int $userId,
        public string $sessionId,
        public int $issuedAt,
        public int $expiresAt,
    ) {
    }
}
