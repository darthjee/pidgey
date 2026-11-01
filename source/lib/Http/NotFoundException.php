<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * 404 Not Found, including unknown routes and resources of other organizations.
 */
class NotFoundException extends HttpException
{
    /**
     * Builds the exception.
     *
     * @param string|null $message Human-readable message, or null for "Not Found".
     * @param string|null $code    Specific error code, or null for NOT_FOUND.
     */
    public function __construct(?string $message = null, ?string $code = null)
    {
        parent::__construct(404, $code, $message);
    }
}
