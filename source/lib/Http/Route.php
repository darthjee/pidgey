<?php

declare(strict_types=1);

namespace Pidgey\Http;

use LogicException;

/**
 * One registered route: method, path pattern with ":name" params, handler and middlewares.
 *
 * A pattern ending in "/*" is a prefix route: "/assets/*" matches "/assets/" followed by anything,
 * including further "/"; nothing is captured for the rest. The regex is compiled the first time
 * the route is tried.
 */
final class Route
{
    private const PARAM = '/:([a-zA-Z_][a-zA-Z0-9_]*)/';

    private const PREFIX_SUFFIX = '/*';

    private ?string $regex = null;

    /**
     * Builds the route.
     *
     * @param string   $method       Upper-case HTTP method.
     * @param string   $pattern      Path pattern, e.g. "/smtp-connections/:id.json".
     * @param string   $handlerClass RequestHandler subclass to build on a match.
     * @param string[] $middlewares  Route middleware class names, in order.
     *
     * @throws LogicException When the pattern repeats a param name or has a "*" other than a final "/*".
     */
    public function __construct(
        private readonly string $method,
        private readonly string $pattern,
        private readonly string $handlerClass,
        private readonly array $middlewares = [],
    ) {
        preg_match_all(self::PARAM, $pattern, $matches);
        $names = $matches[1];

        if (count($names) !== count(array_unique($names))) {
            throw new LogicException("Route {$method} {$pattern} repeats a param name.");
        }

        if (str_contains($pattern, '*') && (substr_count($pattern, '*') > 1 || !$this->isPrefix())) {
            throw new LogicException("Route {$method} {$pattern} may only use \"*\" as a final \"/*\".");
        }
    }

    /**
     * Returns the upper-case HTTP method.
     *
     * @return string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Returns the path pattern as registered.
     *
     * @return string
     */
    public function pattern(): string
    {
        return $this->pattern;
    }

    /**
     * Returns the pattern with every param name replaced by ":", so "/a/:id" and "/a/:x" compare equal.
     *
     * @return string
     */
    public function normalizedPattern(): string
    {
        return (string) preg_replace(self::PARAM, ':', $this->pattern);
    }

    /**
     * Returns the compiled regex, e.g. "#^/smtp\-connections/(?<id>[^/]+)/test\.json$#",
     * or "#^/assets/.*$#" for the prefix route "/assets/*".
     *
     * @return string
     */
    public function regex(): string
    {
        return $this->regex ??= $this->compile();
    }

    /**
     * Matches the request: the method and the path must both match; the query is ignored.
     *
     * @param Request $request The request to match.
     *
     * @return RouteMatch|null The match with the captured params, or null.
     */
    public function match(Request $request): ?RouteMatch
    {
        if ($request->method() !== $this->method) {
            return null;
        }

        if (preg_match($this->regex(), $request->path(), $matches) !== 1) {
            return null;
        }

        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

        return new RouteMatch($this->pattern, $this->handlerClass, $this->middlewares, $params);
    }

    /**
     * Tells whether the pattern is a prefix route, i.e. ends with "/*".
     *
     * @return boolean
     */
    private function isPrefix(): bool
    {
        return str_ends_with($this->pattern, self::PREFIX_SUFFIX);
    }

    /**
     * Compiles the pattern: literals quoted, each param a named group, the whole anchored.
     *
     * A prefix route compiles the part before "*" and ends with ".*" instead.
     *
     * @return string
     */
    private function compile(): string
    {
        $isPrefix = $this->isPrefix();
        $pattern = $isPrefix ? substr($this->pattern, 0, -1) : $this->pattern;
        $parts = preg_split(self::PARAM, $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';

        foreach ($parts as $index => $part) {
            $regex .= $index % 2 === 0 ? preg_quote($part, '#') : "(?<{$part}>[^/]+)";
        }

        $tail = $isPrefix ? '.*' : '';

        return "#^{$regex}{$tail}$#";
    }
}
