<?php

declare(strict_types=1);

namespace Pidgey\Email;

use DateTimeImmutable;

/**
 * The in-flight lease of an organization's test email, handed back to release it.
 */
final readonly class TestEmailLease
{
    /**
     * Builds the lease.
     *
     * @param integer           $organizationId Organization that holds the lease.
     * @param integer           $connectionId   Connection being tested (for the log only).
     * @param DateTimeImmutable $busyUntil      The lease end that was stored.
     */
    public function __construct(
        public int $organizationId,
        public int $connectionId,
        public DateTimeImmutable $busyUntil,
    ) {
    }
}
