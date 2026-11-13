<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * How a session protects the connection.
 *
 * The send flow maps the connection's `encryption` onto it, so the transport never sees a
 * model type.
 */
enum TlsMode
{
    /** No TLS: the session runs in clear text. */
    case Plain;

    /** Clear-text connect, then a mandatory `STARTTLS` upgrade (never downgraded). */
    case StartTls;

    /** TLS handshake right after the TCP connect (implicit TLS). */
    case Implicit;
}
