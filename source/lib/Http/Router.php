<?php

declare(strict_types=1);

namespace Pidgey\Http;

use LogicException;

/**
 * Maps a request to a handler class: routes are tried in registration order, first match wins.
 *
 * When nothing matches, match() returns null; the dispatcher answers the 404.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, true> Keys "<METHOD> <normalized pattern>" already registered. */
    private array $registered = [];

    /**
     * Builds the router.
     *
     * @param string[] $globalMiddlewares Middleware class names run before every route's own.
     */
    public function __construct(private readonly array $globalMiddlewares = [])
    {
    }

    /**
     * Returns the global middleware class names, unchanged.
     *
     * @return list<class-string>
     */
    public function globalMiddlewares(): array
    {
        return $this->globalMiddlewares;
    }

    /**
     * Registers one route.
     *
     * @param string   $method       HTTP method; upper-cased when stored.
     * @param string   $path         Path pattern, e.g. "/smtp-connections/:id.json".
     * @param string   $handlerClass Subclass of RequestHandler.
     * @param string[] $middlewares  Route middleware class names, run after the global ones.
     *
     * @return void
     *
     * @throws LogicException When the handler is not a RequestHandler, a middleware is also global,
     *                        the path repeats a param name, or the method and pattern are taken.
     */
    public function add(string $method, string $path, string $handlerClass, array $middlewares = []): void
    {
        $method = strtoupper($method);

        if (!is_subclass_of($handlerClass, RequestHandler::class)) {
            throw new LogicException("Route {$method} {$path}: {$handlerClass} is not a RequestHandler.");
        }

        foreach ($middlewares as $middleware) {
            if (in_array($middleware, $this->globalMiddlewares, true)) {
                throw new LogicException("Route {$method} {$path}: {$middleware} is already a global middleware.");
            }
        }

        $route = new Route($method, $path, $handlerClass, $middlewares);
        $key = $method . ' ' . $route->normalizedPattern();

        if (isset($this->registered[$key])) {
            throw new LogicException("Route {$method} {$path} is already registered.");
        }

        $this->registered[$key] = true;
        $this->routes[] = $route;
    }

    /**
     * Registers a GET route.
     *
     * @param string   $path         Path pattern.
     * @param string   $handlerClass Subclass of RequestHandler.
     * @param string[] $middlewares  Route middleware class names.
     *
     * @return void
     */
    public function get(string $path, string $handlerClass, array $middlewares = []): void
    {
        $this->add('GET', $path, $handlerClass, $middlewares);
    }

    /**
     * Registers a POST route.
     *
     * @param string   $path         Path pattern.
     * @param string   $handlerClass Subclass of RequestHandler.
     * @param string[] $middlewares  Route middleware class names.
     *
     * @return void
     */
    public function post(string $path, string $handlerClass, array $middlewares = []): void
    {
        $this->add('POST', $path, $handlerClass, $middlewares);
    }

    /**
     * Registers a PATCH route.
     *
     * @param string   $path         Path pattern.
     * @param string   $handlerClass Subclass of RequestHandler.
     * @param string[] $middlewares  Route middleware class names.
     *
     * @return void
     */
    public function patch(string $path, string $handlerClass, array $middlewares = []): void
    {
        $this->add('PATCH', $path, $handlerClass, $middlewares);
    }

    /**
     * Registers a DELETE route.
     *
     * @param string   $path         Path pattern.
     * @param string   $handlerClass Subclass of RequestHandler.
     * @param string[] $middlewares  Route middleware class names.
     *
     * @return void
     */
    public function delete(string $path, string $handlerClass, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handlerClass, $middlewares);
    }

    /**
     * Returns the first route matching the request's method and path, or null.
     *
     * @param Request $request The request to route.
     *
     * @return RouteMatch|null
     */
    public function match(Request $request): ?RouteMatch
    {
        foreach ($this->routes as $route) {
            $match = $route->match($request);

            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }
}
