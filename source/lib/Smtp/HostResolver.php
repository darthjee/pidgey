<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * Resolves a host name to its IP addresses, once per send (`email/send-flow.md#host-check`).
 */
interface HostResolver
{
    /**
     * Returns the IP addresses of the host: the A addresses, then the AAAA addresses.
     *
     * @param string $host The host name.
     *
     * @return list<string> The addresses; empty when the host does not resolve.
     */
    public function resolve(string $host): array;
}
