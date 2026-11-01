<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Throwable;

/**
 * Dispatches one request: route match, handler and middlewares, with every error formatted.
 *
 * Later steps plug in here: the CORS step (before routing and on the final response), request
 * logging, the HandlerFactory and the per-request Container.
 */
final class FrontController
{
    private const ROUTES_FILE = __DIR__ . '/../../config/routes.php';

    /**
     * Builds the front controller.
     *
     * @param Router         $router         Routes to dispatch to.
     * @param ErrorFormatter $errorFormatter Turns any error into the JSON error envelope.
     */
    public function __construct(
        private readonly Router $router,
        private readonly ErrorFormatter $errorFormatter = new ErrorFormatter(),
    ) {
    }

    /**
     * Production entry point: builds the Request and the Router, dispatches, then sends the response.
     *
     * @return void
     */
    public static function run(): void
    {
        $errorFormatter = new ErrorFormatter();

        try {
            $request = new Request();
            $response = (new self(self::loadRouter(self::ROUTES_FILE), $errorFormatter))->handle($request);
        } catch (Throwable $error) {
            $response = $errorFormatter->format($error);
        }

        (new ResponseSender())->send($response);
    }

    /**
     * Dispatches the request and returns the final response; produces no output.
     *
     * No match answers 404 "Cannot <METHOD> <path>". Any Throwable becomes the error envelope.
     *
     * @param Request $request The request to dispatch.
     *
     * @return Response
     */
    public function handle(Request $request): Response
    {
        try {
            $match = $this->router->match($request);

            if ($match === null) {
                throw new NotFoundException('Cannot ' . $request->method() . ' ' . $request->path());
            }

            $processing = new ProcessingRequest($request, $match->params);
            $handler = new ($match->handlerClass)();
            $middlewares = array_map(
                static fn (string $class): Middleware => new $class(),
                [...$this->router->globalMiddlewares(), ...$match->middlewares],
            );

            return $handler->handle($processing, $middlewares);
        } catch (Throwable $error) {
            return $this->errorFormatter->format($error);
        }
    }

    /**
     * Loads the Router returned by a route file, in a scope of its own.
     *
     * @param string $file Path of the route file.
     *
     * @return Router
     */
    private static function loadRouter(string $file): Router
    {
        return require $file;
    }
}
