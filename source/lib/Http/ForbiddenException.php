<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * 403 Forbidden, including cross-site requests rejected by the origin check.
 */
class ForbiddenException extends HttpException
{
    /**
     * Builds the exception.
     *
     * @param string|null $message Human-readable message, or null for "Forbidden".
     * @param string|null $code    Specific error code, or null for FORBIDDEN.
     */
    public function __construct(?string $message = null, ?string $code = null)
    {
        parent::__construct(403, $code, $message);
    }
}
