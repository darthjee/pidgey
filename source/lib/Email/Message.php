<?php

declare(strict_types=1);

namespace Pidgey\Email;

use LogicException;

/**
 * One message to send: the recipient, the subject and at least one body (text or HTML).
 *
 * It does no validation beyond the body rule: callers pass validated fields, and
 * `MimeBuilder` refuses unsafe headers as a backstop.
 */
final readonly class Message
{
    /**
     * Builds the message.
     *
     * @param string      $to      The recipient address.
     * @param string      $subject The subject, UTF-8.
     * @param string|null $text    The plain-text body, or null.
     * @param string|null $html    The HTML body, or null.
     *
     * @throws LogicException When neither body is given.
     */
    public function __construct(
        public string $to,
        public string $subject,
        public ?string $text,
        public ?string $html,
    ) {
        if ($text === null && $html === null) {
            throw new LogicException('Message: a text or html body is required');
        }
    }
}
