<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * 423 Locked, e.g. an account edit while the caller is locked out after too many failed attempts.
 */
class LockedException extends HttpException
{
    /**
     * Builds the exception.
     *
     * @param string|null $message Human-readable message, or null for "Locked".
     * @param string|null $code    Specific error code, or null for LOCKED.
     */
    public function __construct(?string $message = null, ?string $code = null)
    {
        parent::__construct(423, $code, $message);
    }
}
