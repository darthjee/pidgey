<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * Result of a successful Router::match(): what to build and the captured route params.
 */
final readonly class RouteMatch
{
    /**
     * Builds the match.
     *
     * @param string                $pattern      Registered path pattern, e.g. "/a/:id.json".
     * @param string                $handlerClass RequestHandler subclass to build.
     * @param string[]              $middlewares  Route middleware class names, in order.
     * @param array<string, string> $params       Captured params, decoded.
     */
    public function __construct(
        public string $pattern,
        public string $handlerClass,
        public array $middlewares,
        public array $params,
    ) {
    }
}
