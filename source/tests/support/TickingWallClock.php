<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use Pidgey\Infra\WallClock;

/**
 * A clock that moves one minute forward on every read, so a second read would show.
 */
final class TickingWallClock implements WallClock
{
    public function __construct(private int $timestamp)
    {
    }

    public function now(): DateTimeImmutable
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimestamp($this->timestamp);
        $this->timestamp += 60;

        return $now;
    }
}
