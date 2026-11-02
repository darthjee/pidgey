<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * Immutable view of one HTTP request, read from the PHP globals.
 *
 * Every value can be overridden through constructor options, so tests need no web server.
 * Only the method, raw URI, path and query are read for now; headers, cookies and the body come later.
 *
 * Pending: a decoded NUL byte in the path must answer 400 (BadRequestException), which needs
 * the error wrapper from a later issue; path() does not reject it yet.
 */
final readonly class Request
{
    private const DEFAULT_METHOD = 'GET';

    private string $method;

    private ?string $uri;

    /**
     * Builds the request from the PHP globals, with optional overrides.
     *
     * @param array{method?: string, uri?: string} $options Overrides for REQUEST_METHOD (method)
     *                                                      and REQUEST_URI (uri).
     */
    public function __construct(array $options = [])
    {
        $method = $options['method'] ?? $_SERVER['REQUEST_METHOD'] ?? self::DEFAULT_METHOD;
        $uri = $options['uri'] ?? $_SERVER['REQUEST_URI'] ?? null;

        $this->method = strtoupper((string) $method);
        $this->uri = is_string($uri) ? $uri : null;
    }

    /**
     * Returns the request method, upper-case.
     *
     * @return string
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Returns the raw REQUEST_URI exactly as received, or "/" when it is missing.
     *
     * @return string
     */
    public function uri(): string
    {
        return $this->uri ?? '/';
    }

    /**
     * Returns the percent-decoded path, or "/" when the URI is missing, unparsable or has no path.
     *
     * @return string
     */
    public function path(): string
    {
        $path = $this->uri === null ? null : parse_url($this->uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        return rawurldecode($path);
    }

    /**
     * Returns the raw query string, or an empty string when there is none.
     *
     * @return string
     */
    public function query(): string
    {
        $query = $this->uri === null ? null : parse_url($this->uri, PHP_URL_QUERY);

        return is_string($query) ? $query : '';
    }
}
