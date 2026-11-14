<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * 429 Too Many Requests, with the `Retry-After` header.
 */
class TooManyRequestsException extends HttpException
{
    /**
     * Builds the exception.
     *
     * @param string  $message    Human-readable message.
     * @param integer $retryAfter Whole seconds until the caller may retry (at least 1).
     */
    public function __construct(string $message, private readonly int $retryAfter)
    {
        parent::__construct(429, null, $message);
    }

    /**
     * Returns the whole seconds until the caller may retry.
     *
     * @return integer
     */
    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Returns the `Retry-After` header line.
     *
     * @return list<string>
     */
    public function headers(): array
    {
        return ['Retry-After: ' . $this->retryAfter];
    }
}
