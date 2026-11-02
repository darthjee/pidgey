<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * 413 Content Too Large: the request body exceeds the accepted size.
 *
 * Carries no code nor message, so the envelope shows HTTP_413 and the reason phrase.
 */
class PayloadTooLargeException extends HttpException
{
    /**
     * Builds the exception.
     */
    public function __construct()
    {
        parent::__construct(413);
    }
}
