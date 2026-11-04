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

    /**
     * Debug view (`var_dump`, `print_r`): the token without its digest and the user reduced to
     * its id and username (no password digest, no email).
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'token' => [
                'id' => $this->token->id,
                'userId' => $this->token->userId,
                'sessionUuid' => $this->token->sessionUuid,
            ],
            'user' => ['id' => $this->user->id, 'username' => $this->user->username],
        ];
    }
}
