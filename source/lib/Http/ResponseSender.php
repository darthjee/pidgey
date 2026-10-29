<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Closure;

/**
 * Sends a Response: status, header lines, then the body.
 */
final class ResponseSender
{
    private Closure $statusEmitter;

    private Closure $headerEmitter;

    /**
     * Builds the sender with optional emitters, so tests can see what is sent.
     *
     * @param callable|null $statusEmitter Receives the status code; defaults to http_response_code().
     * @param callable|null $headerEmitter Receives each header line; defaults to header($line, false).
     */
    public function __construct(?callable $statusEmitter = null, ?callable $headerEmitter = null)
    {
        $this->statusEmitter = $statusEmitter === null
            ? http_response_code(...)
            : Closure::fromCallable($statusEmitter);
        $this->headerEmitter = $headerEmitter === null
            ? static fn (string $line) => header($line, false)
            : Closure::fromCallable($headerEmitter);
    }

    /**
     * Emits the status, each header line in order, then echoes the body.
     *
     * Headers are emitted without replacing, so repeated names (e.g. Set-Cookie) survive.
     *
     * @param Response $response The response to send.
     *
     * @return void
     */
    public function send(Response $response): void
    {
        ($this->statusEmitter)($response->status());

        foreach ($response->headers() as $line) {
            ($this->headerEmitter)($line);
        }

        echo $response->body();
    }
}
