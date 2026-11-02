<?php

declare(strict_types=1);

namespace Pidgey\Infra;

/**
 * Strict parser for bare origins (scheme://host[:port]) and Host header values.
 *
 * Shared by the origin check and the ALLOWED_ORIGINS validation so both accept the same
 * forms. parse_url() is not used: it accepts paths, user info and other forms an origin
 * never has.
 */
final class OriginParser
{
    /** The default port of each accepted scheme. */
    public const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /** host[:port], with bracketed IPv6 hosts; no user info, path, query or fragment. */
    private const HOST_PATTERN = '(\[[0-9A-Fa-f:.]+\]|[^\s\/?#@:\[\]]+)(?::([0-9]{1,5}))?';

    private const MAX_PORT = 65535;

    /**
     * Parses a bare http or https origin; an omitted port is the scheme's default.
     *
     * @param string $origin Origin, e.g. "https://example.com:8443".
     *
     * @return array{scheme: string, host: string, port: integer}|null Null when it cannot be parsed.
     */
    public static function parseOrigin(string $origin): ?array
    {
        if (preg_match('/^(https?):\/\/' . self::HOST_PATTERN . '$/D', $origin, $matches) !== 1) {
            return null;
        }

        $scheme = $matches[1];
        $port = self::parsePort($matches[3] ?? '', self::DEFAULT_PORTS[$scheme]);

        return $port === null ? null : ['scheme' => $scheme, 'host' => $matches[2], 'port' => $port];
    }

    /**
     * Parses a host[:port] Host header value.
     *
     * @param string  $host        Host value, e.g. "example.com" or "[::1]:8080".
     * @param integer $defaultPort Port used when the value has none.
     *
     * @return array{host: string, port: integer}|null Null when it cannot be parsed.
     */
    public static function parseHost(string $host, int $defaultPort): ?array
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
