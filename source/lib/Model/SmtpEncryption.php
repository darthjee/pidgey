<?php

declare(strict_types=1);

namespace Pidgey\Model;

/**
 * Transport encryption of an SMTP connection (`smtp_connections.encryption`); each case's value
 * is the database value.
 */
enum SmtpEncryption: string
{
    case None = 'none';
    case Tls = 'tls';
    case Ssl = 'ssl';
}
