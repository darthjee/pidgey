<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * The step of an SMTP session a failure happened at.
 */
enum SmtpStep
{
    /** The TCP connect, and the TLS handshake in implicit mode. */
    case Connect;

    /** The server's `220` greeting. */
    case Greeting;

    /** The `EHLO` command (before or after `STARTTLS`). */
    case Ehlo;

    /** The `STARTTLS` command and its handshake. */
    case StartTls;

    /** The `AUTH` exchange. */
    case Auth;

    /** The `MAIL FROM` command. */
    case MailFrom;

    /** A `RCPT TO` command. */
    case RcptTo;

    /** The `DATA` command. */
    case Data;

    /** The message content and its final `.`. */
    case EndOfData;
}
