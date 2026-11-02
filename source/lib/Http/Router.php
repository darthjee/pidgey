<?php

declare(strict_types=1);

namespace Pidgey\Http;

use LogicException;

/**
 * Maps a request to a handler class: routes are tried in registration order, first match wins.
 *
 * When no route matches, the GET fallback (if registered) is tried last, whatever its registration
 * position: it takes extensionless GET paths outside "/v1/". Otherwise match() returns null and the
 * dispatcher answers the 404.
 */
final class Router
{
    /** Pattern reported in the RouteMatch of the GET fallback. */
    public const FALLBACK_PATTERN = '*';

    private const FALLBACK_METHOD = 'GET';

    private const FALLBACK_EXCLUDED_PREFIX = '/v1/';

    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, true> Keys "<METHOD> <normalized pattern>" already registered. */
    private array $registered = [];

    /** @var string|null Handler class of the GET fallback, kept apart from $routes so it is tried last. */
    private ?string $fallbackHandlerClass = null;

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
     * Registers the GET fallback, tried after every other route; it has no route middlewares.
     *
     * @param string $handlerClass Subclass of RequestHandler.
     *
     * @return void
     *
     * @throws LogicException When the handler is not a RequestHandler or a fallback is already registered.
     */
    public function fallback(string $handlerClass): void
    {
        if (!is_subclass_of($handlerClass, RequestHandler::class)) {
            throw new LogicException("GET fallback: {$handlerClass} is not a RequestHandler.");
        }

        if ($this->fallbackHandlerClass !== null) {
            throw new LogicException('GET fallback is already registered.');
        }

        $this->fallbackHandlerClass = $handlerClass;
    }

    /**
     * Returns the first route matching the request's method and path, then the GET fallback, or null.
     *
     * The fallback's RouteMatch carries the pattern FALLBACK_PATTERN ("*"), no middlewares and no params.
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

        if ($this->fallbackHandlerClass === null || !$this->appliesToFallback($request)) {
            return null;
        }

        return new RouteMatch(self::FALLBACK_PATTERN, $this->fallbackHandlerClass, [], []);
    }

    /**
     * Tells whether the GET fallback takes the request: GET, extensionless last segment, outside "/v1/".
     *
     * @param Request $request The unmatched request.
     *
     * @return bool
     */
    private function appliesToFallback(Request $request): bool
    {
        if ($request->method() !== self::FALLBACK_METHOD) {
            return false;
        }

        $path = $request->path();

        if (str_starts_with($path, self::FALLBACK_EXCLUDED_PREFIX)) {
            return false;
        }

        $lastSegment = substr($path, (int) strrpos($path, '/') + 1);

        return !str_contains($lastSegment, '.');
    }
}
