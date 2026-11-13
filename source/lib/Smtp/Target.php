<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * Where a session connects: the checked IP, its port, the TLS mode and the host name the
 * certificate must match.
 *
 * It does no validation: the send flow passes checked values.
 */
final readonly class Target
{
    /**
     * Builds the target.
     *
     * @param string  $host    The connection's host: the TLS peer name and SNI.
     * @param string  $address The checked IP address to connect to.
     * @param integer $port    TCP port.
     * @param TlsMode $tlsMode How the connection is protected.
     */
    public function __construct(
        public string $host,
        public string $address,
        public int $port,
        public TlsMode $tlsMode,
    ) {
    }
}
