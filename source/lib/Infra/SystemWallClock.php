<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use DateTimeImmutable;
use DateTimeZone;

/**
 * WallClock backed by the system time.
 */
final class SystemWallClock implements WallClock
{
    /**
     * Returns the current system time, in UTC.
     *
     * @return DateTimeImmutable
     */
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
