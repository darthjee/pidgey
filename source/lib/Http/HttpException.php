<?php

declare(strict_types=1);

namespace Pidgey\Http;

use RuntimeException;

/**
 * Error that maps to an HTTP status and the JSON error envelope.
 *
 * A null code or message lets ErrorFormatter fall back to the category code and the
 * standard reason phrase of the status.
 */
class HttpException extends RuntimeException
{
    /**
     * Builds the exception.
     *
     * @param integer                   $status       HTTP status code.
     * @param string|null               $errorCode    Specific or category error code, or null for the fallback.
     * @param string|null               $errorMessage Human-readable message, or null for the reason phrase.
     * @param list<string>|null         $details      Validation details, or null when there are none.
     * @param array<string, mixed>|null $smtp         SMTP server reply, or null when there is none.
     */
    public function __construct(
        private readonly int $status,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorMessage = null,
        private readonly ?array $details = null,
        private readonly ?array $smtp = null,
    ) {
        parent::__construct($errorMessage ?? '', $status);
    }

    /**
     * Returns the HTTP status code.
     *
     * @return integer
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Returns the error code, or null when the formatter must fall back.
     *
     * @return string|null
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Returns the error message, or null when the formatter must fall back.
     *
     * @return string|null
     */
    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Returns the validation details, or null when there are none.
     *
     * @return list<string>|null
     */
    public function details(): ?array
    {
        return $this->details;
    }

    /**
     * Returns the SMTP server reply, or null when there is none.
     *
     * @return array<string, mixed>|null
     */
    public function smtp(): ?array
    {
        return $this->smtp;
    }
}
