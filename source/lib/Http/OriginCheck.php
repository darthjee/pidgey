<?php

declare(strict_types=1);

namespace Pidgey\Http;

use Pidgey\Infra\OriginParser;

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

    private const REJECTION_MESSAGE = 'Cross-site request rejected';

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

        $parsedOrigin = OriginParser::parseOrigin($origin);
        $parsedHost = $parsedOrigin === null || $host === null
            ? null
            : OriginParser::parseHost($host, OriginParser::DEFAULT_PORTS[$parsedOrigin['scheme']]);

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
}
