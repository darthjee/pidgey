<?php

declare(strict_types=1);

namespace Pidgey\Tests\Unit\Infra;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Pidgey\Infra\SystemClock;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    public function testSleepAdvancesMonotonicTime(): void
    {
        $clock = new SystemClock();
        $start = $clock->now();

        $clock->sleep(0.01);

        $this->assertGreaterThanOrEqual(0.009, $clock->now() - $start);
    }
}
