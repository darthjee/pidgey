<?php

declare(strict_types=1);

namespace Pidgey\Http;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Formats the dates of API bodies: ISO 8601 in UTC with milliseconds, e.g.
 * `2026-10-17T12:00:00.000Z` (see api/conventions/format.md).
 */
final class Timestamp
{
    private const FORMAT = 'Y-m-d\TH:i:s.v\Z';

    /**
     * Formats a date and time, converted to UTC first whatever its time zone.
     *
     * @param DateTimeImmutable $value Date and time to format.
     *
     * @return string
     */
    public static function format(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }
}
