<?php

declare(strict_types=1);

namespace Pidgey\Tests\Support;

use InvalidArgumentException;
use Pidgey\Infra\Clock;

/**
 * Monotonic clock that stands still: `sleep()` returns at once and moves `now()` forward.
 */
final class FrozenClock implements Clock
{
    public function __construct(private float $now = 0.0)
    {
    }

    public function now(): float
    {
        return $this->now;
    }

    /**
     * Adds the seconds to `now()`; a negative value throws, as `usleep()` does.
     *
     * @throws InvalidArgumentException When the duration is negative.
     */
    public function sleep(float $seconds): void
    {
        if ($seconds < 0) {
            throw new InvalidArgumentException('sleep: negative duration');
        }

        $this->now += $seconds;
    }
}
