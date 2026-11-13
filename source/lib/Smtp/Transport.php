<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use SensitiveParameter;

/**
 * Delivers one message over one SMTP session.
 */
interface Transport
{
    /**
     * Runs one SMTP session and returns the raw reply to the final `.`.
     *
     * @param Target           $target      Where to connect, and how to protect the connection.
     * @param SessionSettings  $settings    Timeouts, CA bundle and `EHLO` name.
     * @param Credentials|null $credentials The `AUTH` credentials, or null to send without `AUTH`.
     * @param Envelope         $envelope    The `MAIL FROM` sender and the `RCPT TO` recipients.
     * @param string           $message     The MIME message, not yet dot-stuffed.
     *
     * @return SmtpResult
     *
     * @throws SmtpFailure When the session fails, classified by code and step.
     */
    public function send(
        Target $target,
        SessionSettings $settings,
        #[SensitiveParameter] ?Credentials $credentials,
        Envelope $envelope,
        #[SensitiveParameter] string $message,
    ): SmtpResult;
}
