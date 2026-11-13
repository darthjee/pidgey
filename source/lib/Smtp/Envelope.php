<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use InvalidArgumentException;

/**
 * The SMTP envelope of a session: the `MAIL FROM` sender and the `RCPT TO` recipients.
 */
final readonly class Envelope
{
    /** @var list<string> */
    public array $recipients;

    /**
     * Builds the envelope.
     *
     * @param string             $from       The envelope sender address.
     * @param array<int, string> $recipients The recipient addresses; at least one.
     *
     * @throws InvalidArgumentException When there is no recipient.
     */
    public function __construct(public string $from, array $recipients)
    {
        if ($recipients === []) {
            throw new InvalidArgumentException('Envelope: at least one recipient is required');
        }

        $this->recipients = array_values($recipients);
    }
}
