<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * 401 Unauthorized: missing or invalid credentials.
 */
class UnauthorizedException extends HttpException
{
    /**
     * Builds the exception.
     *
     * @param string|null $message Human-readable message, or null for "Unauthorized".
     * @param string|null $code    Specific error code, or null for UNAUTHORIZED.
     */
    public function __construct(?string $message = null, ?string $code = null)
    {
        parent::__construct(401, $code, $message);
    }
}
