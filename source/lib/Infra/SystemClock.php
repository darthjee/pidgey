<?php

declare(strict_types=1);

namespace Pidgey\Infra;

/**
 * Clock backed by the system monotonic timer.
 */
final class SystemClock implements Clock
{
    /**
     * Returns a monotonic timestamp in seconds.
     *
     * @return float
     */
    public function now(): float
    {
        return hrtime(true) / 1e9;
    }

    /**
     * Pauses for the given number of seconds.
     *
     * @param float $seconds Duration of the pause.
     *
     * @return void
     */
    public function sleep(float $seconds): void
    {
        usleep((int) round($seconds * 1e6));
    }
}
