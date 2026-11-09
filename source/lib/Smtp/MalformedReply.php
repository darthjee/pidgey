<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use RuntimeException;

/**
 * The server sent a reply that breaks RFC 5321 §4.2 or the parser's limits, or closed the
 * connection in the middle of a reply.
 *
 * The message is fixed and never carries the received bytes.
 */
final class MalformedReply extends RuntimeException
{
    /**
     * Builds the failure, with its fixed message.
     */
    public function __construct()
    {
        parent::__construct('malformed SMTP reply');
    }
}
