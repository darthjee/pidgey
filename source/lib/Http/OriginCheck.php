<?php

declare(strict_types=1);

namespace Pidgey\Http;

/**
 * Global middleware: Pidgey's CSRF protection (api/conventions/origin-check.md).
 *
 * Every method except GET, HEAD and OPTIONS is checked, so unknown methods fail closed.
 * A rejected request throws ForbiddenException; the middleware never answers itself.
 */
final class OriginCheck extends BaseMiddleware
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private const ALLOWED_FETCH_SITES = ['same-origin', 'none'];

    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    private const REJECTION_MESSAGE = 'Cross-site request rejected';

    private const HOST_PATTERN = '(\[[0-9A-Fa-f:.]+\]|[^\s\/?#@:\[\]]+)(?::([0-9]{1,5}))?';

    private const MAX_PORT = 65535;

    private readonly TrustedOrigins $trusted;

    /**
     * Builds the middleware.
     *
     * @param TrustedOrigins|null $trusted Trusted origins; defaults to TrustedOrigins::fromEnv().
     */
    public function __construct(?TrustedOrigins $trusted = null)
    {
        $this->trusted = $trusted ?? TrustedOrigins::fromEnv();
    }

    /**
     * Rejects cross-site state-changing requests.
     *
     * @param ProcessingRequest $request The request being dispatched.
     *
     * @return void
     *
     * @throws ForbiddenException When the request is cross-site.
     */
    public function processRequest(ProcessingRequest $request): void
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return;
        }

        $headers = $request->request();
        $fetchSite = $headers->header('Sec-Fetch-Site');
        $origin = $headers->header('Origin');

        if ($fetchSite !== null) {
            $this->checkFetchSite($fetchSite, $origin);

            return;
        }

        if ($origin !== null) {
            $this->checkOrigin($origin, $headers->header('Host'));
        }
    }

    /**
     * Rule 2: Sec-Fetch-Site present.
     *
     * @param string      $fetchSite Sec-Fetch-Site value.
     * @param string|null $origin    Origin value, or null when absent.
     *
     * @return void
     *
     * @throws ForbiddenException When the request is cross-site and the origin is not trusted.
     */
    private function checkFetchSite(string $fetchSite, ?string $origin): void
    {
        if (in_array($fetchSite, self::ALLOWED_FETCH_SITES, true)) {
            return;
        }

        if ($origin !== null && $this->trusted->contains($origin)) {
            return;
        }

        throw new ForbiddenException(self::REJECTION_MESSAGE);
    }

    /**
     * Rule 4: Origin present without Sec-Fetch-Site.
     *
     * @param string      $origin Origin value.
     * @param string|null $host   Host value, or null when absent.
     *
     * @return void
     *
     * @throws ForbiddenException When the origin is neither trusted nor the request's own host.
     */
    private function checkOrigin(string $origin, ?string $host): void
    {
        if ($this->trusted->contains($origin)) {
            return;
        }

        $parsedOrigin = self::parseOrigin($origin);
        $parsedHost = $parsedOrigin === null || $host === null
            ? null
            : self::parseHost($host, self::DEFAULT_PORTS[$parsedOrigin['scheme']]);

        if ($parsedOrigin === null || $parsedHost === null || !self::sameHostAndPort($parsedOrigin, $parsedHost)) {
            throw new ForbiddenException(self::REJECTION_MESSAGE);
        }
    }

    /**
     * Compares host (ignoring case) and port.
     *
     * @param array{scheme: string, host: string, port: integer} $origin Parsed Origin.
     * @param array{host: string, port: integer}                 $host   Parsed Host.
     *
     * @return boolean
     */
    private static function sameHostAndPort(array $origin, array $host): bool
    {
        return strtolower($origin['host']) === strtolower($host['host']) && $origin['port'] === $host['port'];
    }

    /**
     * Parses a bare scheme://host[:port] origin.
     *
     * @param string $origin Origin value.
     *
     * @return array{scheme: string, host: string, port: integer}|null Null when it cannot be parsed.
     */
    private static function parseOrigin(string $origin): ?array
    {
        if (preg_match('/^(https?):\/\/' . self::HOST_PATTERN . '$/D', $origin, $matches) !== 1) {
            return null;
        }

        $scheme = $matches[1];
        $port = self::parsePort($matches[3] ?? '', self::DEFAULT_PORTS[$scheme]);

        return $port === null ? null : ['scheme' => $scheme, 'host' => $matches[2], 'port' => $port];
    }

    /**
     * Parses a host[:port] Host header.
     *
     * @param string  $host        Host value.
     * @param integer $defaultPort Port used when the Host has none.
     *
     * @return array{host: string, port: integer}|null Null when it cannot be parsed.
     */
    private static function parseHost(string $host, int $defaultPort): ?array
    {
        if (preg_match('/^' . self::HOST_PATTERN . '$/D', $host, $matches) !== 1) {
            return null;
        }

        $port = self::parsePort($matches[2] ?? '', $defaultPort);

        return $port === null ? null : ['host' => $matches[1], 'port' => $port];
    }

    /**
     * Parses a port, defaulting when it is absent.
     *
     * @param string  $port        Digits, or "" when absent.
     * @param integer $defaultPort Port used when absent.
     *
     * @return integer|null Null for port 0 or above 65535.
     */
    private static function parsePort(string $port, int $defaultPort): ?int
    {
        if ($port === '') {
            return $defaultPort;
        }

        $value = (int) $port;

        return $value < 1 || $value > self::MAX_PORT ? null : $value;
    }
}
