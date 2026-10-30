<?php

declare(strict_types=1);

namespace Pidgey\Repository;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Converts PHP values to the form bound in SQL statements.
 */
final class Sql
{
    /**
     * Formats a date and time as a `DATETIME` value (`Y-m-d H:i:s`) in UTC.
     *
     * The value is converted to UTC first, whatever its time zone.
     *
     * @param DateTimeImmutable $value Date and time to bind.
     *
     * @return string
     */
    public static function dateTime(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
