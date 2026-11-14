<?php

declare(strict_types=1);

namespace Pidgey\Model;

use DateTimeImmutable;
use LogicException;
use UnexpectedValueException;

/**
 * The state of the `smtp_test_limits` row of one organization: its fixed window and its lease.
 */
final readonly class SmtpTestLimit
{
    /**
     * Builds the value from its column values.
     *
     * @param DateTimeImmutable      $windowStartedAt Start of the current window, in UTC.
     * @param integer                $attempts        Test emails counted in the current window.
     * @param DateTimeImmutable|null $busyUntil       End of the in-flight lease, in UTC, or null when free.
     */
    public function __construct(
        public DateTimeImmutable $windowStartedAt,
        public int $attempts,
        public ?DateTimeImmutable $busyUntil,
    ) {
    }

    /**
     * Builds the value from a `FETCH_ASSOC` row keyed by column name.
     *
     * @param array<string, mixed> $row Row holding the window, count and lease columns.
     *
     * @return self
     *
     * @throws LogicException           When a column is missing.
     * @throws UnexpectedValueException When a value has the wrong shape.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            Row::dateTime($row, 'window_started_at'),
            Row::int($row, 'attempts'),
            Row::nullableDateTime($row, 'busy_until'),
        );
    }
}
