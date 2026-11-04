<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * 409 Conflict, e.g. a username or email that is already taken on register.
 */
class ConflictException extends HttpException
{
    /**
     * Builds the exception.
     *
     * @param string|null $message Human-readable message, or null for "Conflict".
     * @param string|null $code    Specific error code, or null for CONFLICT.
     */
    public function __construct(?string $message = null, ?string $code = null)
    {
        parent::__construct(409, $code, $message);
    }
}
