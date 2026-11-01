<?php

declare(strict_types=1);

namespace Pidgey\Infra;

use DateTimeImmutable;

/**
 * Wall-clock time source, so timestamps are testable.
 */
interface WallClock
{
    /**
     * Returns the current time, in UTC.
     *
     * @return DateTimeImmutable
     */
    public function now(): DateTimeImmutable;
}
