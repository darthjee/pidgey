<?php

declare(strict_types=1);

namespace Pidgey\Smtp;

use Pidgey\Infra\Clock;

/**
 * A point in time an operation must finish by, read from a monotonic clock.
 *
 * The session deadline is started at connect time; each wait derives a child with
 * `within()`, so it never outlives the session (`min(its own timeout, time remaining)`).
 */
final class Deadline
{
    /**
     * Builds a deadline expiring at the given clock time.
     *
     * @param Clock $clock     Monotonic clock.
     * @param float $expiresAt Clock time of expiry, in seconds.
     */
    private function __construct(private readonly Clock $clock, private readonly float $expiresAt)
    {
    }

    /**
     * Starts a deadline that expires the given number of seconds from now.
     *
     * @param Clock $clock   Monotonic clock.
     * @param float $seconds Time allowed.
     *
     * @return self
     */
    public static function start(Clock $clock, float $seconds): self
    {
        return new self($clock, $clock->now() + $seconds);
    }

    /**
     * Returns the seconds left, never below zero.
     *
     * @return float
     */
    public function remaining(): float
    {
        return max(0.0, $this->expiresAt - $this->clock->now());
    }

    /**
     * Tells whether no time is left.
     *
     * @return boolean
     */
    public function expired(): bool
    {
        return $this->remaining() <= 0.0;
    }

    /**
     * Returns a child deadline expiring after the given seconds, or with this one if sooner.
     *
     * @param float $seconds Time allowed to the child.
     *
     * @return self
     */
    public function within(float $seconds): self
    {
        return new self($this->clock, min($this->expiresAt, $this->clock->now() + $seconds));
    }
}
