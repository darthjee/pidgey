<?php

declare(strict_types=1);

namespace Pidgey\Http;

use InvalidArgumentException;

/**
 * HTTP response: status, header lines and body.
 */
class Response
{
    /** @var list<string> */
    private array $headers = [];

    /**
     * Builds the response.
     *
     * @param string  $body   Response body.
     * @param integer $status HTTP status code.
     */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
    ) {
    }

    /**
     * Returns the response body.
     *
     * @return string
     */
    public function body(): string
    {
        return $this->body;
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
     * Returns the header lines, in the order they were added.
     *
     * @return list<string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Appends a header line.
     *
     * @param string $line Full header line, e.g. "Content-Type: text/plain".
     *
     * @return void
     *
     * @throws InvalidArgumentException When the line contains CR or LF.
     */
    public function addHeader(string $line): void
    {
        if (strpbrk($line, "\r\n") !== false) {
            throw new InvalidArgumentException('Header lines must not contain CR or LF.');
        }

        $this->headers[] = $line;
    }
}
