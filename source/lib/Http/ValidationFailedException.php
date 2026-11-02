<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * 400 VALIDATION_FAILED: the request body failed validation.
 *
 * The message is every detail joined by "; ", and details() lists them one by one.
 */
class ValidationFailedException extends HttpException
{
    private const CODE = 'VALIDATION_FAILED';

    /**
     * Builds the exception.
     *
     * @param string[] $details One message per failed rule, in declaration order.
     */
    public function __construct(array $details)
    {
        parent::__construct(400, self::CODE, implode('; ', $details), $details);
    }
}
