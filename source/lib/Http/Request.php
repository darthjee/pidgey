<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * Immutable view of one HTTP request, read from the PHP globals.
 *
 * Every value can be overridden through constructor options, so tests need no web server.
 * The method, raw URI, path, query and headers are read for now; cookies and the body come later.
 * Headers are read only from $_SERVER, never from getallheaders().
 *
 * Pending: a decoded NUL byte in the path must answer 400 (BadRequestException), which needs
 * the error wrapper from a later issue; path() does not reject it yet.
 */
final readonly class Request
{
    private const DEFAULT_METHOD = 'GET';

    private const UNPREFIXED_HEADERS = ['CONTENT_TYPE', 'CONTENT_LENGTH'];

    private const HEADER_PREFIX = 'HTTP_';

    private string $method;

    private ?string $uri;

    /** @var array<string, string> Request headers keyed by their $_SERVER key. */
    private array $headers;

    /**
     * Builds the request from the PHP globals, with optional overrides.
     *
     * @param array{method?: string, uri?: string, headers?: array<string, string>} $options Overrides for
     *        REQUEST_METHOD (method), REQUEST_URI (uri) and the request headers in $_SERVER (headers,
     *        keyed by header name or $_SERVER key).
     */
    public function __construct(array $options = [])
    {
        $method = $options['method'] ?? $_SERVER['REQUEST_METHOD'] ?? self::DEFAULT_METHOD;
        $uri = $options['uri'] ?? $_SERVER['REQUEST_URI'] ?? null;

        $this->method = strtoupper((string) $method);
        $this->uri = is_string($uri) ? $uri : null;
        $this->headers = array_key_exists('headers', $options)
            ? self::normalizeHeaderOverrides($options['headers'])
            : self::serverHeaders();
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

    /**
     * Returns the raw value of a request header, or the default when it was not sent.
     *
     * The lookup ignores case: the name is upper-cased, "-" becomes "_" and HTTP_ is
     * prefixed, except for Content-Type and Content-Length. A header sent empty returns "".
     *
     * @param string      $name    Header name, e.g. "Content-Type" or "CONTENT_TYPE".
     * @param string|null $default Value returned when the header is missing.
     *
     * @return string|null
     */
    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[self::serverKey($name)] ?? $default;
    }

    /**
     * Turns a header name into its $_SERVER key.
     *
     * @param string $name Header name.
     *
     * @return string
     */
    private static function serverKey(string $name): string
    {
        $key = str_replace('-', '_', strtoupper($name));

        return in_array($key, self::UNPREFIXED_HEADERS, true) ? $key : self::HEADER_PREFIX . $key;
    }

    /**
     * Normalizes the headers override, accepting header names or $_SERVER keys.
     *
     * @param array<string, string> $headers Header values keyed by name or $_SERVER key.
     *
     * @return array<string, string>
     */
    private static function normalizeHeaderOverrides(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $isServerKey = str_starts_with((string) $name, self::HEADER_PREFIX)
                || in_array((string) $name, self::UNPREFIXED_HEADERS, true);
            $key = $isServerKey ? (string) $name : self::serverKey((string) $name);
            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }

    /**
     * Reads the request headers from $_SERVER.
     *
     * @return array<string, string>
     */
    private static function serverHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            $isHeader = is_string($key)
                && (str_starts_with($key, self::HEADER_PREFIX) || in_array($key, self::UNPREFIXED_HEADERS, true));

            if ($isHeader && is_string($value)) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }
}
