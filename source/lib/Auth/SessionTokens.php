<?php

declare(strict_types=1);

namespace Pidgey\Auth;

use Pidgey\Model\User;
use SensitiveParameter;
use SensitiveParameterValue;

/**
 * The tokens of a newly started session: the plain refresh token, the access token (JWT),
 * the session id and the user they belong to.
 *
 * The token values are secrets: they are kept in `SensitiveParameterValue` holders, so
 * `var_export`, `json_encode`, `(array)`, `get_object_vars`, `print_r` and `var_dump` never show
 * them (and the object cannot be serialized). They are read through `refreshToken()` and
 * `accessToken()` only, and must never be logged.
 */
final readonly class SessionTokens
{
    private const REDACTED = '[redacted]';

    private SensitiveParameterValue $refreshToken;

    private SensitiveParameterValue $accessToken;

    /**
     * Builds the value.
     *
     * @param string $refreshToken The plain refresh token, for the client only.
     * @param string $accessToken  The access token (JWT), whose `sid` is the session id.
     * @param string $sessionUuid  The session id.
     * @param User   $user         The session's user.
     */
    public function __construct(
        #[SensitiveParameter] string $refreshToken,
        #[SensitiveParameter] string $accessToken,
        public string $sessionUuid,
        public User $user,
    ) {
        $this->refreshToken = new SensitiveParameterValue($refreshToken);
        $this->accessToken = new SensitiveParameterValue($accessToken);
    }

    /**
     * Returns the plain refresh token, for the client only.
     *
     * @return string
     */
    public function refreshToken(): string
    {
        return $this->refreshToken->getValue();
    }

    /**
     * Returns the access token (JWT).
     *
     * @return string
     */
    public function accessToken(): string
    {
        return $this->accessToken->getValue();
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
