<?php

declare(strict_types=1);

namespace Pidgey\Infra;

/**
 * Monotonic time source able to pause, so retry loops are testable.
 */
interface Clock
{
    /**
     * Returns a monotonic timestamp in seconds.
     *
     * @return float
     */
    public function now(): float;

    /**
     * Pauses for the given number of seconds.
     *
     * @param float $seconds Duration of the pause.
     *
     * @return void
     */
    public function sleep(float $seconds): void;
}
