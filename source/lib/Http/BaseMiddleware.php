<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * Middleware with no-op hooks; subclasses override only what they need.
 */
abstract class BaseMiddleware implements Middleware
{
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
