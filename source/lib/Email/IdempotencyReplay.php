<?php

declare(strict_types=1);

namespace Pidgey\Email;

/**
 * A stored answer to send again verbatim, with the `Idempotent-Replayed: true` header.
 */
final readonly class IdempotencyReplay
{
    /**
     * Builds the replay.
     *
     * @param integer $status HTTP status of the stored answer.
     * @param string  $body   JSON body of the stored answer, as stored.
     */
    public function __construct(public int $status, public string $body)
    {
    }
}
