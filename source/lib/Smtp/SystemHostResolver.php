<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * Resolves through the system: A records with `gethostbynamel()` (so the hosts file is
 * honored), AAAA records with a DNS query, whose errors count as no AAAA records.
 */
final class SystemHostResolver implements HostResolver
{
    /**
     * Returns the A addresses, then the AAAA addresses, without duplicates.
     *
     * @param string $host The host name.
     *
     * @return list<string> The addresses; empty when the host does not resolve.
     */
    public function resolve(string $host): array
    {
        return array_values(array_unique([...self::ipv4($host), ...self::ipv6($host)]));
    }

    /**
     * Returns the A addresses, from the system resolver.
     *
     * @param string $host The host name.
     *
     * @return list<string>
     */
    private static function ipv4(string $host): array
    {
        return gethostbynamel($host) ?: [];
    }

    /**
     * Returns the AAAA addresses, from a DNS query; a lookup error gives none.
     *
     * @param string $host The host name.
     *
     * @return list<string>
     */
    private static function ipv6(string $host): array
    {
        $records = @dns_get_record($host, DNS_AAAA);

        if (!is_array($records)) {
            return [];
        }

        $addresses = [];

        foreach ($records as $record) {
            if (is_array($record) && isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return $addresses;
    }
}
