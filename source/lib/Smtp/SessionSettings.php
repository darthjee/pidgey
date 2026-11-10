<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * The SMTP transport settings shared by every session: timeouts, CA bundle and `EHLO` name.
 */
final readonly class SessionSettings
{
    /**
     * Builds the settings.
     *
     * @param float       $connectTimeout Seconds allowed to open the connection (TCP and TLS).
     * @param float       $commandTimeout Seconds allowed for each command's reply.
     * @param float       $totalTimeout   Seconds allowed for the whole session.
     * @param string|null $caFile         Absolute path of the CA bundle, or null for the system one.
     * @param string|null $ehloName       Name sent in `EHLO`, or null to use the local address.
     */
    public function __construct(
        public float $connectTimeout,
        public float $commandTimeout,
        public float $totalTimeout,
        public ?string $caFile,
        public ?string $ehloName,
    ) {
    }
}
