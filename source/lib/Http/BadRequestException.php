<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * 400 Bad Request, e.g. a malformed JSON body or a decoded NUL byte in the path.
 */
class BadRequestException extends HttpException
{
    /**
     * Builds the exception.
     *
     * @param string|null $message Human-readable message, or null for "Bad Request".
     * @param string|null $code    Specific error code, or null for BAD_REQUEST.
     */
    public function __construct(?string $message = null, ?string $code = null)
    {
        parent::__construct(400, $code, $message);
    }
}
