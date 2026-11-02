<?php

declare(strict_types=1);

namespace Pidgey\Http;

use JsonException;
use stdClass;

/**
 * Immutable view of one HTTP request, read from the PHP globals.
 *
 * Every value can be overridden through constructor options, so tests need no web server.
 * The method, raw URI, path, query, headers and body are read for now; cookies come later.
 * Headers are read only from $_SERVER, never from getallheaders().
 *
 * The raw body and the decoded JSON body are read lazily, on the first call, and cached.
 */
final readonly class Request
{
    /** Largest accepted request body, in bytes (1 MiB). */
    public const MAX_BODY_BYTES = 1048576;

    private const DEFAULT_METHOD = 'GET';

    private const JSON_MEDIA_TYPE = 'application/json';

    private const JSON_CHARSET = 'utf-8';

    private const JSON_DEPTH = 64;

    private const UNPREFIXED_HEADERS = ['CONTENT_TYPE', 'CONTENT_LENGTH'];

    private const HEADER_PREFIX = 'HTTP_';

    private string $method;

    private ?string $uri;

    /** @var array<string, string> Request headers keyed by their $_SERVER key. */
    private array $headers;

    /** Raw body; left uninitialized until first read, unless given as an option. */
    private string $body;

    /** @var array<string, mixed> Decoded JSON body; left uninitialized until json() first runs. */
    private array $json;

    /**
     * Builds the request from the PHP globals, with optional overrides.
     *
     * @param array{method?: string, uri?: string, headers?: array<string, string>, body?: string} $options Overrides
     *        for REQUEST_METHOD (method), REQUEST_URI (uri), the request headers in $_SERVER (headers, keyed by
     *        header name or $_SERVER key) and php://input (body).
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

        if (array_key_exists('body', $options)) {
            $this->body = (string) $options['body'];
        }
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
     *
     * @throws BadRequestException When the decoded path contains a NUL byte.
     */
    public function path(): string
    {
        $path = $this->uri === null ? null : parse_url($this->uri, PHP_URL_PATH);

        if (!is_string($path) || $path === '') {
            return '/';
        }

        $decoded = rawurldecode($path);

        if (str_contains($decoded, "\0")) {
            throw new BadRequestException();
        }

        return $decoded;
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
     * Returns the raw request body, read once from php://input and capped at MAX_BODY_BYTES + 1 bytes.
     *
     * @return string
     */
    public function body(): string
    {
        if (!isset($this->body)) {
            $read = file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
            $this->body = is_string($read) ? $read : '';
        }

        return $this->body;
    }

    /**
     * Returns the decoded JSON object body, parsed on the first call and cached.
     *
     * An empty body gives []. Unknown fields are kept; nested objects become arrays.
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedMediaTypeException When a non-empty body is not UTF-8 application/json.
     * @throws PayloadTooLargeException      When the body is larger than MAX_BODY_BYTES.
     * @throws BadRequestException           When the body is not valid JSON or not a JSON object.
     */
    public function json(): array
    {
        if (!isset($this->json)) {
            $this->json = $this->parseJson();
        }

        return $this->json;
    }

    /**
     * Applies the JSON body rules: empty body, media type, size, syntax, then top-level object.
     *
     * A Content-Length over the limit means a non-empty body, so it is rejected without reading it.
     *
     * @return array<string, mixed>
     */
    private function parseJson(): array
    {
        $announcedTooLarge = $this->contentLengthExceedsLimit();

        if (!$announcedTooLarge && $this->body() === '') {
            return [];
        }

        if (!$this->isJsonMediaType()) {
            throw new UnsupportedMediaTypeException();
        }

        if ($announcedTooLarge || strlen($this->body()) > self::MAX_BODY_BYTES) {
            throw new PayloadTooLargeException();
        }

        try {
            $decoded = json_decode($this->body(), false, self::JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestException();
        }

        if (!$decoded instanceof stdClass) {
            throw new BadRequestException();
        }

        return self::objectToArray($decoded);
    }

    /**
     * Tells whether a numeric Content-Length header announces more than MAX_BODY_BYTES.
     *
     * @return boolean
     */
    private function contentLengthExceedsLimit(): bool
    {
        $length = trim((string) $this->header('Content-Length', ''));

        if (!ctype_digit($length)) {
            return false;
        }

        $digits = ltrim($length, '0');

        return strlen($digits) > strlen((string) self::MAX_BODY_BYTES) || (int) $digits > self::MAX_BODY_BYTES;
    }

    /**
     * Tells whether Content-Type is application/json, with no charset other than utf-8 (both ignoring case).
     *
     * @return boolean
     */
    private function isJsonMediaType(): bool
    {
        $parts = explode(';', (string) $this->header('Content-Type', ''));

        if (strcasecmp(trim(array_shift($parts)), self::JSON_MEDIA_TYPE) !== 0) {
            return false;
        }

        foreach ($parts as $parameter) {
            [$name, $value] = array_pad(explode('=', $parameter, 2), 2, '');
            $isCharset = strcasecmp(trim($name), 'charset') === 0;

            if ($isCharset && strcasecmp(trim(trim($value), '"'), self::JSON_CHARSET) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Converts a decoded JSON object to an array, recursively turning nested objects into arrays.
     *
     * @param stdClass $object The decoded object.
     *
     * @return array<string, mixed>
     */
    private static function objectToArray(stdClass $object): array
    {
        return self::objectsToArrays(get_object_vars($object));
    }

    /**
     * Converts nested objects of a decoded JSON array to arrays.
     *
     * @param array<mixed> $values Decoded values.
     *
     * @return array<mixed>
     */
    private static function objectsToArrays(array $values): array
    {
        foreach ($values as $key => $value) {
            if ($value instanceof stdClass) {
                $values[$key] = self::objectToArray($value);
            } elseif (is_array($value)) {
                $values[$key] = self::objectsToArrays($value);
            }
        }

        return $values;
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
