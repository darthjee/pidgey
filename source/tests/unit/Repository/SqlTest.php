<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Repository\Sql;

#[CoversClass(Sql::class)]
final class SqlTest extends TestCase
{
    public function testFormatsUtcDateTime(): void
    {
        $value = new DateTimeImmutable('2026-03-04 05:06:07.891', new DateTimeZone('UTC'));

        $this->assertSame('2026-03-04 05:06:07', Sql::dateTime($value));
    }

    public function testConvertsNonUtcDateTimeToUtc(): void
    {
        $value = new DateTimeImmutable('2026-03-04 01:30:00', new DateTimeZone('America/Sao_Paulo'));

        $this->assertSame('2026-03-04 04:30:00', Sql::dateTime($value));
    }

    public function testConvertsOffsetAcrossMidnight(): void
    {
        $value = new DateTimeImmutable('2026-01-01 00:15:00+02:00');

        $this->assertSame('2025-12-31 22:15:00', Sql::dateTime($value));
    }

    public function testLeavesInputUnchanged(): void
    {
        $zone = new DateTimeZone('Asia/Tokyo');
        $value = new DateTimeImmutable('2026-03-04 09:00:00', $zone);

        Sql::dateTime($value);

        $this->assertSame('Asia/Tokyo', $value->getTimezone()->getName());
    }
}
