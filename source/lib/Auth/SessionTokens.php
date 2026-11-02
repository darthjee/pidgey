<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Model\User;
use SensitiveParameter;

/**
 * The tokens of a newly started session: the plain refresh token, the access token (JWT),
 * the session id and the user they belong to.
 *
 * The token values are secrets: they are redacted from debug output and must never be logged.
 */
final readonly class SessionTokens
{
    private const REDACTED = '[redacted]';

    /**
     * Builds the value.
     *
     * @param string $refreshToken The plain refresh token, for the client only.
     * @param string $accessToken  The access token (JWT), whose `sid` is the session id.
     * @param string $sessionUuid  The session id.
     * @param User   $user         The session's user.
     */
    public function __construct(
        #[SensitiveParameter] public string $refreshToken,
        #[SensitiveParameter] public string $accessToken,
        public string $sessionUuid,
        public User $user,
    ) {
    }

    /**
     * Debug view (`var_dump`, `print_r`) with both tokens redacted.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'refreshToken' => self::REDACTED,
            'accessToken' => self::REDACTED,
            'sessionUuid' => $this->sessionUuid,
            'user' => $this->user,
        ];
    }
}
