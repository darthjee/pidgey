<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Pidgey\Container;

/**
 * Middleware with no-op hooks; subclasses override only what they need.
 */
abstract class BaseMiddleware implements Middleware
{
    /**
     * Builds the middleware with no arguments; subclasses with constructor arguments override it.
     *
     * @param Container $container The request's container (unused by default).
     *
     * @return static
     */
    public static function build(Container $container): static
    {
        return new static();
    }

    /**
     * Does nothing.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return void
     */
    public function processRequest(ProcessingRequest $request): void
    {
    }

    /**
     * Returns the response unchanged.
     *
     * @param ProcessingRequest $request  The request being dispatched.
     * @param Response          $response The response so far.
     *
     * @return Response
     */
    public function processResponse(ProcessingRequest $request, Response $response): Response
    {
        return $response;
    }
}
