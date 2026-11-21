<?php

declare(strict_types=1);

namespace Pidgey\Email;

/**
 * The handle of a reserved `Idempotency-Key`, to hand back to `IdempotencyKeys::finish()`.
 */
final readonly class IdempotencyReservation
{
    /**
     * Builds the handle.
     *
     * @param integer $organizationId Organization the key belongs to.
     * @param string  $keyHash        Lowercase hex SHA-256 of the raw key.
     */
    public function __construct(public int $organizationId, public string $keyHash)
    {
    }
}
