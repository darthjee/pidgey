<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

/**
 * The outcome of a delivered message: the raw reply to the final `.`.
 */
final readonly class SmtpResult
{
    /**
     * Builds the result.
     *
     * @param Reply $reply The raw 2xx reply to the final `.`.
     */
    public function __construct(private Reply $reply)
    {
    }

    /**
     * Returns the raw reply to the final `.`.
     *
     * @return Reply
     */
    public function reply(): Reply
    {
        return $this->reply;
    }

    /**
     * Returns the code of the reply to the final `.`.
     *
     * @return integer
     */
    public function code(): int
    {
        return $this->reply->code();
    }
}
