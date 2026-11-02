<?php

declare(strict_types=1);

namespace Pidgey\Http;

use LogicException;
use Pidgey\Container;

/**
 * Builds a route's handler and middlewares for one request, through their static build().
 */
final class HandlerFactory
{
    /**
     * Builds the factory.
     *
     * @param Container $container The request's container, passed to every build().
     */
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Builds the handler of the given class.
     *
     * @param string $class Fully-qualified class name of a RequestHandler subclass.
     *
     * @return RequestHandler
     *
     * @throws LogicException When the class is not a RequestHandler subclass.
     */
    public function handler(string $class): RequestHandler
    {
        if (!is_subclass_of($class, RequestHandler::class)) {
            throw new LogicException("Not a request handler: {$class}");
        }

        return $class::build($this->container);
    }

    /**
     * Builds each middleware class, keeping the order.
     *
     * @param string[] $classes Fully-qualified class names of Middleware implementations.
     *
     * @return list<Middleware>
     *
     * @throws LogicException When a class is not a middleware.
     */
    public function middlewares(array $classes): array
    {
        $middlewares = [];

        foreach ($classes as $class) {
            if (!is_subclass_of($class, Middleware::class)) {
                throw new LogicException("Not a middleware: {$class}");
            }

            $middlewares[] = $class::build($this->container);
        }

        return $middlewares;
    }
}
