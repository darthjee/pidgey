<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Pidgey\Infra\WallClock;

/**
 * Wall clock that stands still at a given instant, in UTC, until it is advanced.
 */
final class FrozenWallClock implements WallClock
{
    private DateTimeImmutable $now;

    /**
     * @param integer|DateTimeImmutable $at Unix timestamp, or an instant in any time zone.
     */
    public function __construct(int|DateTimeImmutable $at)
    {
        $instant = is_int($at) ? (new DateTimeImmutable())->setTimestamp($at) : $at;

        $this->now = $instant->setTimezone(new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    /**
     * Moves the clock forward.
     *
     * @throws InvalidArgumentException When the number of seconds is negative.
     */
    public function advance(int $seconds): void
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('advance: negative duration');
        }

        $this->now = $this->now->modify("+{$seconds} seconds");
    }
}
