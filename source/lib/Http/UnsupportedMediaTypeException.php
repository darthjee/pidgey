<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * 415 Unsupported Media Type: the request body is not UTF-8 application/json.
 *
 * Carries no code nor message, so the envelope shows HTTP_415 and the reason phrase.
 */
class UnsupportedMediaTypeException extends HttpException
{
    /**
     * Builds the exception.
     */
    public function __construct()
    {
        parent::__construct(415);
    }
}
