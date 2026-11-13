<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * The classified outcome of a failed SMTP session (`email/failures.md`).
 */
enum FailureCode: string
{
    /** The connection could not be opened, or the peer closed before the greeting. */
    case ConnectionFailed = 'SMTP_CONNECTION_FAILED';

    /** `STARTTLS` was missing or refused, or the TLS handshake or certificate check failed. */
    case TlsFailed = 'SMTP_TLS_FAILED';

    /** Authentication failed, was impossible, or was required. */
    case AuthFailed = 'SMTP_AUTH_FAILED';

    /** The server refused with a permanent (5xx) reply. */
    case Rejected = 'SMTP_REJECTED';

    /** The server refused with a transient (4xx) reply. */
    case TemporaryFailure = 'SMTP_TEMPORARY_FAILURE';

    /** The server broke the protocol: a malformed reply, or a close mid-session. */
    case ProtocolError = 'SMTP_PROTOCOL_ERROR';

    /** A wait ran out of time once the connection was established. */
    case Timeout = 'SMTP_TIMEOUT';
}
