<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Closure;
use Pidgey\Container;
use Pidgey\Infra\ConfigValidator;
use RuntimeException;
use Throwable;

/**
 * Dispatches one request: route match, handler and middlewares, with every error formatted.
 *
 * Each handle() call creates one fresh Container and builds the handler and the middlewares
 * through a HandlerFactory over it. The container is lazy, so a route that needs no service
 * (e.g. /health.json or the static files) opens no database connection.
 *
 * The CORS step answers preflights before routing and adds its headers to every final response,
 * errors included. Request logging is a later step.
 */
final class FrontController
{
    private const ROUTES_FILE = __DIR__ . '/../../config/routes.php';

    private readonly Closure $containerFactory;

    private readonly Cors $cors;

    /**
     * Builds the front controller.
     *
     * @param Router         $router           Routes to dispatch to.
     * @param ErrorFormatter $errorFormatter   Turns any error into the JSON error envelope.
     * @param Closure|null   $containerFactory Closure(): Container, called once per request;
     *                                         defaults to `new Container()`.
     * @param Cors|null      $cors             CORS step; defaults to `new Cors()` (origins from env).
     */
    public function __construct(
        private readonly Router $router,
        private readonly ErrorFormatter $errorFormatter = new ErrorFormatter(),
        ?Closure $containerFactory = null,
        ?Cors $cors = null,
    ) {
        $this->containerFactory = $containerFactory ?? static fn (): Container => new Container();
        $this->cors = $cors ?? new Cors();
    }

    /**
     * Production entry point: validates the configuration, builds the Request and the Router,
     * dispatches, then sends the response.
     *
     * An invalid configuration is logged (failure names only, never values) and answered with
     * the 500 INTERNAL_ERROR envelope.
     *
     * @param Closure|null $validator Closure(): list<string>, returns the configuration failures;
     *                                defaults to ConfigValidator::validate().
     *
     * @return void
     */
    public static function run(?Closure $validator = null): void
    {
        $errorFormatter = new ErrorFormatter();
        $validator ??= static fn (): array => (new ConfigValidator())->validate();

        try {
            self::assertValidConfiguration($validator);
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
     * A CORS preflight is answered before routing. No match answers 404 "Cannot <METHOD> <path>".
     * Any Throwable becomes the error envelope. The CORS headers are added to the final response.
     *
     * @param Request $request The request to dispatch.
     *
     * @return Response
     */
    public function handle(Request $request): Response
    {
        try {
            $response = $this->cors->preflight($request) ?? $this->dispatch($request);
        } catch (Throwable $error) {
            $response = $this->errorFormatter->format($error);
        }

        return $this->cors->addHeaders($request, $response);
    }

    /**
     * Routes the request and runs the matched handler with its middlewares.
     *
     * @param Request $request The request to dispatch.
     *
     * @return Response
     *
     * @throws NotFoundException When no route matches.
     */
    private function dispatch(Request $request): Response
    {
        $match = $this->router->match($request);

        if ($match === null) {
            throw new NotFoundException('Cannot ' . $request->method() . ' ' . $request->path());
        }

        $processing = new ProcessingRequest($request, $match->params);
        $factory = new HandlerFactory(($this->containerFactory)());
        $handler = $factory->handler($match->handlerClass);
        $middlewares = $factory->middlewares([...$this->router->globalMiddlewares(), ...$match->middlewares]);

        return $handler->handle($processing, $middlewares);
    }

    /**
     * Throws when the configuration validation reports any failure.
     *
     * @param Closure $validator Closure(): list<string>, returns the configuration failures.
     *
     * @return void
     *
     * @throws RuntimeException When the configuration is invalid; the message lists the failure names.
     */
    private static function assertValidConfiguration(Closure $validator): void
    {
        $failures = $validator();

        if ($failures !== []) {
            throw new RuntimeException('Invalid configuration: ' . implode(', ', $failures));
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
