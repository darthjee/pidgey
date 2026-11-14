<?php

declare(strict_types=1);

namespace Pidgey\Email;

/**
 * The fixed content of the test email sent through an SMTP connection.
 */
final class TestEmail
{
    public const SUBJECT = 'Pidgey test email';

    public const TEXT = 'This is a test email sent by Pidgey to check an SMTP connection.';

    /**
     * Builds the test message for a recipient: the fixed subject, a text body and no HTML.
     *
     * @param string $to The recipient address (the stored email of an organization user).
     *
     * @return Message
     */
    public static function message(string $to): Message
    {
        return new Message($to, self::SUBJECT, self::TEXT, null);
    }
}
