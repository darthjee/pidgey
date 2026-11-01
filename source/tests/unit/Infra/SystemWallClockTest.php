<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\SystemWallClock;

#[CoversClass(SystemWallClock::class)]
final class SystemWallClockTest extends TestCase
{
    public function testReturnsTheCurrentTimeInUtc(): void
    {
        $before = new DateTimeImmutable();
        $now = (new SystemWallClock())->now();
        $after = new DateTimeImmutable();

        $this->assertSame('UTC', $now->getTimezone()->getName());
        $this->assertGreaterThanOrEqual($before, $now);
        $this->assertLessThanOrEqual($after, $now);
    }
}
