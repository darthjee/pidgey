<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * Why a stream operation failed.
 */
enum StreamFailureKind
{
    /** The TCP connection could not be established. */
    case Connect;

    /** The TLS handshake or the certificate check failed. */
    case Tls;

    /** The operation ran out of time. */
    case Timeout;

    /** The peer closed the connection before sending any byte. */
    case Closed;

    /** Bytes arrived before the `STARTTLS` handshake. */
    case UnexpectedData;

    /**
     * Returns the fixed message of the kind.
     *
     * @return string
     */
    public function message(): string
    {
        return match ($this) {
            self::Connect => 'SMTP connection failed',
            self::Tls => 'TLS negotiation failed',
            self::Timeout => 'SMTP operation timed out',
            self::Closed => 'connection closed by the peer',
            self::UnexpectedData => 'unexpected data before the TLS handshake',
        };
    }
}
