<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * Base class for handlers: runs the middleware hooks around process().
 */
abstract class RequestHandler
{
    /**
     * Runs the request hooks, then the early response or process(), then every response hook.
     *
     * Request hooks run in order and stop after the first one that sets an early response;
     * process() is then skipped. Response hooks all run, in the same order, even after an
     * early response, each receiving the previous one's result. Nothing is caught.
     *
     * @param ProcessingRequest $request     The request being dispatched.
     * @param Middleware[]      $middlewares Middlewares as a list, in registration order.
     *
     * @return Response
     */
    final public function handle(ProcessingRequest $request, array $middlewares): Response
    {
        foreach ($middlewares as $middleware) {
            $middleware->processRequest($request);

            if ($request->hasResponse()) {
                break;
            }
        }

        $response = $request->response() ?? $this->process($request);

        foreach ($middlewares as $middleware) {
            $response = $middleware->processResponse($request, $response);
        }

        return $response;
    }

    /**
     * Builds the response for the request.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return Response
     */
    abstract protected function process(ProcessingRequest $request): Response;
}
