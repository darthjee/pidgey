<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Http;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Http\Timestamp;

#[CoversClass(Timestamp::class)]
final class TimestampTest extends TestCase
{
    public function testFormatsUtcWithMilliseconds(): void
    {
        $this->assertSame(
            '2026-10-17T12:00:00.000Z',
            Timestamp::format(new DateTimeImmutable('2026-10-17 12:00:00', new DateTimeZone('UTC'))),
        );
    }

    public function testConvertsToUtcFirst(): void
    {
        $this->assertSame(
            '2026-10-17T10:00:00.250Z',
            Timestamp::format(new DateTimeImmutable('2026-10-17T12:00:00.250+02:00')),
        );
    }
}
