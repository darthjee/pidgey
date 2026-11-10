<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * Builds the stream context options of the TLS policy: always verify the certificate and the
 * peer name, TLS 1.2 or 1.3 only, no SNI for IP literals. Self-signed certificates are
 * refused (the PHP default, never overridden).
 */
final class TlsContext
{
    /**
     * Returns the `['ssl' => …]` stream context options for a peer.
     *
     * @param string      $peerName Name the certificate must match (a host name or an IP
     *                              literal, matched against an IP SAN).
     * @param string|null $caFile   Absolute path of the CA bundle, or null for the system one.
     *
     * @return array{ssl: array<string, mixed>}
     */
    public static function options(string $peerName, ?string $caFile): array
    {
        $isIpLiteral = filter_var($peerName, FILTER_VALIDATE_IP) !== false;

        $ssl = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $peerName,
            'SNI_enabled' => !$isIpLiteral,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            'capture_peer_cert' => false,
        ];

        if (!$isIpLiteral) {
            $ssl['SNI_server_name'] = $peerName;
        }

        if ($caFile !== null) {
            $ssl['cafile'] = $caFile;
        }

        return ['ssl' => $ssl];
    }
}
