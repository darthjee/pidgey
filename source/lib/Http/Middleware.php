<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * Request and response hooks run around a handler by RequestHandler::handle().
 */
interface Middleware
{
    /**
     * Request hook: may attach data, answer early with respond(), or throw for any error.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return void
     */
    public function processRequest(ProcessingRequest $request): void;

    /**
     * Response hook: returns the given response (possibly changed) or a replacement.
     *
     * @param ProcessingRequest $request  The request being dispatched.
     * @param Response          $response The response so far.
     *
     * @return Response
     */
    public function processResponse(ProcessingRequest $request, Response $response): Response;
}
